<?php

namespace App\Tests\Controller;

use App\Entity\Bike\Bike;
use App\Entity\Bike\CreateBikeCommand;
use App\Entity\Maintenance\CreateMaintenanceCommand;
use App\Entity\MaintenanceTask\CreateMaintenanceTaskCommand;
use App\Entity\Manufacturer\CreateManufacturerCommand;
use App\Entity\Model\CreateModelCommand;
use App\Entity\Model\Model;
use App\Entity\Part\CreatePartCommand;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\ServiceInterval\ServiceInterval;
use App\Entity\Task\CreateTaskCommand;
use App\Entity\Task\Task;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use App\Entity\Workshop\CreateWorkshopCommand;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * A later service entry must not roll the bike's recorded mileage backwards.
 *
 * These tests fail while a signed-in rider can post a newer service date with an
 * odometer below the mileage already recorded for that bike. They pass once that
 * post no longer lowers the mileage the workshop page shows, and no longer
 * increases the distance remaining on distance-based service reminders.
 */
class MaintenanceOdometerRollbackTest extends WebTestCase
{
    private const RECORDED_MILEAGE = 12000;

    private const ABSOLUTE_INTERVAL_KM = 5000;

    private const RELATIVE_INTERVAL_KM = 2000;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir().'/bikelog-odometer-rollback.db';
        foreach ([$this->databasePath, $this->databasePath.'_test'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $databaseUrl = 'sqlite:///'.$this->databasePath;
        putenv('DATABASE_URL='.$databaseUrl);
        $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['DATABASE_URL'] = $databaseUrl;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ([$this->databasePath, $this->databasePath.'_test'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @dataProvider lowerOdometerReadings
     */
    public function testLaterServiceDoesNotLowerMileageOrDelayDistanceReminders(int $submittedOdometer): void
    {
        static::createClient();
        $seed = $this->seedWorkshopClientBike();
        $expectedRemaining = [
            'Oil change' => self::RECORDED_MILEAGE + self::ABSOLUTE_INTERVAL_KM - self::RECORDED_MILEAGE,
            'Chain adjustment' => self::RECORDED_MILEAGE + self::RELATIVE_INTERVAL_KM - self::RECORDED_MILEAGE,
        ];

        $this->assertMileageAndReminders($seed, self::RECORDED_MILEAGE, $expectedRemaining);

        $client = $this->rebootClient();
        $rider = $this->user($client, 'rider');
        $client->loginUser($rider);

        $crawler = $client->request('GET', '/dashboard/maintenance/new/'.$seed['bikeId'].'?_locale=en');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $values = $form->getPhpValues();
        $formName = array_key_first($values);
        self::assertIsString($formName);

        $values[$formName]['workshop'] = $seed['workshopId'];
        $values[$formName]['bike'] = $seed['bikeId'];
        $values[$formName]['date'] = '01. 06. 2026';
        $values[$formName]['odometer'] = (string) $submittedOdometer;
        $values[$formName]['spentTime'] = '1';
        $values[$formName]['unspecifiedCosts'] = '0';
        $values[$formName]['maintenanceTaskCommands'] = [
            [
                'task' => $seed['oilTaskId'],
                'cost' => '25',
            ],
        ];

        $client->request($form->getMethod(), $form->getUri(), $values);
        self::assertLessThan(500, $client->getResponse()->getStatusCode());

        $this->assertMileageAndReminders($seed, self::RECORDED_MILEAGE, $expectedRemaining);
    }

    /**
     * @return array<string, array{int}>
     */
    public function lowerOdometerReadings(): array
    {
        return [
            'zero' => [0],
            'positive but below the recorded mileage' => [100],
        ];
    }

    /**
     * @param array{bikeId: string, workshopId: string, oilTaskId: string} $seed
     * @param array<string, int> $expectedRemainingByTask
     */
    private function assertMileageAndReminders(array $seed, int $expectedMileage, array $expectedRemainingByTask): void
    {
        $client = $this->rebootClient();
        $owner = $this->user($client, 'workshop-owner');
        $client->loginUser($owner);

        $client->request('GET', '/dashboard/workshop/'.$seed['workshopId'].'?_locale=en');
        self::assertResponseIsSuccessful();
        $workshopPage = $client->getResponse()->getContent();
        self::assertIsString($workshopPage);
        self::assertStringContainsString($this->mileageLine($expectedMileage), $workshopPage);

        $entityManager = $this->entityManager();
        $bike = $entityManager->getRepository(Bike::class)->find($seed['bikeId']);
        self::assertInstanceOf(Bike::class, $bike);
        self::assertSame($expectedMileage, $bike->getOdometer());
        self::assertNull($bike->getLastRefueling());

        $client = $this->rebootClient();
        $rider = $this->user($client, 'rider');
        $client->loginUser($rider);
        $client->request('GET', '/dashboard/bike/'.$seed['bikeId'].'/service-plan?_locale=en');
        self::assertResponseIsSuccessful();
        $servicePlan = $client->getResponse()->getContent();
        self::assertIsString($servicePlan);

        $bike = $this->entityManager()->getRepository(Bike::class)->find($seed['bikeId']);
        self::assertInstanceOf(Bike::class, $bike);
        $statuses = [];
        foreach ($bike->getServiceIntervalStatuses() as $status) {
            $statuses[$status['task']->getName()] = $status;
        }

        foreach ($expectedRemainingByTask as $taskName => $expectedRemaining) {
            self::assertArrayHasKey($taskName, $statuses);
            self::assertSame($expectedRemaining, $statuses[$taskName]['remainingDistance']);
            self::assertSame(
                ServiceInterval::ABSOLUTE_DISTANCE === $statuses[$taskName]['intervalType']
                    || ServiceInterval::RELATIVE_DISTANCE === $statuses[$taskName]['intervalType'],
                true
            );
            self::assertStringContainsString('Due in '.number_format($expectedRemaining).' km', $servicePlan);
        }
    }

    private function mileageLine(int $mileage): string
    {
        return '2024 RollbackMaker RollbackModel ('.number_format($mileage).' km)';
    }

    /**
     * @return array{bikeId: string, workshopId: string, oilTaskId: string}
     */
    private function seedWorkshopClientBike(): array
    {
        $entityManager = $this->entityManager();
        $this->createSchema($entityManager);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $migrator = $this->createMigrator($entityManager);
        $owner = $this->createAccount($migrator, $hasher, 'workshop-owner', 'owner@example.test');
        $rider = $this->createAccount($migrator, $hasher, 'rider', 'rider@example.test');
        $entityManager->persist($owner);
        $entityManager->persist($rider);

        $manufacturerCommand = new CreateManufacturerCommand();
        $manufacturerCommand->name = 'RollbackMaker';
        $manufacturer = $owner->createManufacturer($manufacturerCommand);

        $partCommand = new CreatePartCommand();
        $partCommand->name = 'Engine';
        $part = $manufacturer->createPart($partCommand, $owner);

        $oilTask = $this->createTask($owner, 'Oil change', 'Replace oil', $part);
        $chainTask = $this->createTask($owner, 'Chain adjustment', 'Adjust chain', $part);

        $modelCommand = new CreateModelCommand();
        $modelCommand->name = 'RollbackModel';
        $modelCommand->alterName = 'Std';
        $modelCommand->displacement = 600;
        $modelCommand->yearFrom = 2020;
        $modelCommand->yearTo = 2026;
        $modelCommand->vinRanges = [];
        $modelCommand->fuelTankSize = 14;
        $modelCommand->pictureFilename = '';
        $model = new Model($modelCommand, $manufacturer, $owner);
        $absoluteInterval = $model->createServiceInterval(
            $this->intervalCommand($oilTask, self::ABSOLUTE_INTERVAL_KM, ServiceInterval::ABSOLUTE_DISTANCE),
            $owner
        );
        $relativeInterval = $model->createServiceInterval(
            $this->intervalCommand($chainTask, self::RELATIVE_INTERVAL_KM, ServiceInterval::RELATIVE_DISTANCE),
            $owner
        );

        $workshopCommand = new CreateWorkshopCommand();
        $workshopCommand->name = 'Rollback Workshop';
        $workshop = $owner->createWorkshop($workshopCommand);
        $workshop->addClient($rider, $owner);

        $bikeCommand = new CreateBikeCommand();
        $bikeCommand->model = $model;
        $bikeCommand->nickname = '';
        $bikeCommand->purchasePrice = 1000;
        $bikeCommand->purchaseOdometer = 0;
        $bikeCommand->purchaseDate = new \DateTime('2020-01-01');
        $bikeCommand->year = 2024;
        $bikeCommand->vin = 'ROLLBACKVIN0001';
        $bikeCommand->fuelTanksize = 14;
        $bikeCommand->pictureFilename = '';
        $bike = $rider->createBike($bikeCommand);

        $maintenanceCommand = new CreateMaintenanceCommand();
        $maintenanceCommand->workshop = $workshop;
        $maintenanceCommand->bike = $bike;
        $maintenanceCommand->date = new \DateTime('2024-03-01');
        $maintenanceCommand->odometer = self::RECORDED_MILEAGE;
        $maintenanceCommand->spentTime = 1;
        $maintenanceCommand->unspecifiedCosts = 0;
        $maintenance = $bike->createMaintenance($maintenanceCommand, $rider);
        $oilEntry = $maintenance->createMaintenanceTask($this->taskCommand($oilTask, 40), $rider);
        $chainEntry = $maintenance->createMaintenanceTask($this->taskCommand($chainTask, 15), $rider);

        $entityManager->persist($manufacturer);
        $entityManager->persist($part);
        $entityManager->persist($oilTask);
        $entityManager->persist($chainTask);
        $entityManager->persist($model);
        $entityManager->persist($absoluteInterval);
        $entityManager->persist($relativeInterval);
        $entityManager->persist($workshop);
        $entityManager->persist($bike);
        $entityManager->persist($maintenance);
        $entityManager->persist($oilEntry);
        $entityManager->persist($chainEntry);
        $entityManager->flush();

        self::assertSame(self::RECORDED_MILEAGE, $bike->getOdometer());
        self::assertNull($bike->getLastRefueling());

        return [
            'bikeId' => $bike->getId()->toString(),
            'workshopId' => $workshop->getId()->toString(),
            'oilTaskId' => $oilTask->getId()->toString(),
        ];
    }

    private function createSchema(EntityManagerInterface $entityManager): void
    {
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    private function createMigrator(EntityManagerInterface $entityManager): User
    {
        $migrator = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->writeProperty($migrator, 'id', Uuid::uuid6());
        $this->writeProperty($migrator, 'createdBy', $migrator);
        $this->writeProperty($migrator, 'createdOn', new \DateTime());
        $this->writeProperty($migrator, 'username', 'migrator');
        $this->writeProperty($migrator, 'firstName', 'Database');
        $this->writeProperty($migrator, 'lastName', 'Migrator');
        $this->writeProperty($migrator, 'password', 'placeholder');
        $this->writeProperty($migrator, 'roles', ['ROLE_USER']);
        $this->writeProperty($migrator, 'email', 'migrator@example.test');
        $this->writeProperty($migrator, 'mobile', null);
        $this->writeProperty($migrator, 'isActive', false);
        $this->writeProperty($migrator, 'profilePictureFilename', '');
        $this->writeProperty($migrator, 'bikes', new ArrayCollection());
        $this->writeProperty($migrator, 'workshops', new ArrayCollection());
        $this->writeProperty($migrator, 'ownedWorkshops', new ArrayCollection());

        $entityManager->persist($migrator);
        $entityManager->flush();

        return $migrator;
    }

    private function createAccount(User $migrator, UserPasswordHasherInterface $hasher, string $username, string $email): User
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = $username;
        $command->lastName = 'Tester';
        $command->email = $email;
        $command->mobile = null;
        $command->isRoleAdmin = false;
        $command->password = 'placeholder';
        $command->profilePictureFilename = '';

        return new User($command, $migrator, $hasher);
    }

    private function createTask(User $user, string $name, string $description, $part): Task
    {
        $command = new CreateTaskCommand();
        $command->name = $name;
        $command->description = $description;
        $command->part = $part;
        $command->comment = '';

        return new Task($command, $user);
    }

    private function intervalCommand(Task $task, int $interval, int $intervalType): CreateServiceIntervalCommand
    {
        $command = new CreateServiceIntervalCommand();
        $command->task = $task;
        $command->interval = $interval;
        $command->intervalType = $intervalType;

        return $command;
    }

    private function taskCommand(Task $task, float $cost): CreateMaintenanceTaskCommand
    {
        $command = new CreateMaintenanceTaskCommand();
        $command->task = $task;
        $command->cost = $cost;
        $command->comment = '';

        return $command;
    }

    private function writeProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($object);
        while ($reflection instanceof \ReflectionClass && ! $reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();
        }

        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }

    private function rebootClient(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return static::createClient();
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function user(KernelBrowser $client, string $username): User
    {
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
