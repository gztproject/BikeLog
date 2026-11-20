<?php

namespace App\Tests\Controller\Maintenance;

use App\Entity\Bike\Bike;
use App\Entity\Bike\CreateBikeCommand;
use App\Entity\Maintenance\CreateMaintenanceCommand;
use App\Entity\Maintenance\Maintenance;
use App\Entity\MaintenanceTask\CreateMaintenanceTaskCommand;
use App\Entity\Manufacturer\CreateManufacturerCommand;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Model\CreateModelCommand;
use App\Entity\Model\Model;
use App\Entity\Task\CreateTaskCommand;
use App\Entity\Task\Task;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use App\Entity\Workshop\CreateWorkshopCommand;
use App\Entity\Workshop\Workshop;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The maintenance details route must render a service record only for the rider who owns its bike.
 * These tests fail while any signed-in user who knows the record id can read another rider's visit.
 */
final class MaintenanceShowAuthorizationTest extends WebTestCase
{
    private const PASSWORD = 'placeholder-password';

    private const VICTIM_BIKE = 'Sentinel Bike North';

    private const VICTIM_WORKSHOP = 'Sentinel Workshop North';

    private const VICTIM_TASK = 'Sentinel Valve Adjustment';

    private const VICTIM_ODOMETER = 654321;

    private const ATTACKER_BIKE = 'Sentinel Bike South';

    private const ATTACKER_WORKSHOP = 'Sentinel Workshop South';

    private const ATTACKER_TASK = 'Sentinel Chain Lube';

    private const ATTACKER_ODOMETER = 111111;

    private string $victimMaintenanceId;

    private string $attackerMaintenanceId;

    private string $attackerBikeId;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $_SERVER['APP_DEBUG'] = '0';
        $_ENV['APP_DEBUG'] = '0';

        $kernel = self::bootKernel(['debug' => false]);
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $this->rebuildSchema($em);
        $seed = $this->seedTwoRiders($em);
        $this->victimMaintenanceId = $seed['victimMaintenanceId'];
        $this->attackerMaintenanceId = $seed['attackerMaintenanceId'];
        $this->attackerBikeId = $seed['attackerBikeId'];
        self::ensureKernelShutdown();
    }

    public function testOwnerCanOpenOwnMaintenanceDetails(): void
    {
        $client = $this->signIn('rider-north');

        $client->request('GET', '/dashboard/maintenance/'.$this->victimMaintenanceId);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('maintenance_show', $content);
        self::assertStringContainsString(self::VICTIM_BIKE, $content);
        self::assertStringContainsString(self::VICTIM_WORKSHOP, $content);
        self::assertStringContainsString(self::VICTIM_TASK, $content);
        self::assertStringContainsString('654,321', $content);
        self::assertStringNotContainsString(self::ATTACKER_BIKE, $content);
        self::assertStringNotContainsString(self::ATTACKER_WORKSHOP, $content);
    }

    public function testSignedInRiderCannotViewAnotherRidersMaintenanceRecord(): void
    {
        $client = $this->signIn('rider-south');

        $client->request('GET', '/dashboard/maintenance/'.$this->attackerMaintenanceId);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertStringContainsString(self::ATTACKER_BIKE, (string) $client->getResponse()->getContent());

        $before = $this->maintenanceSnapshot($this->victimMaintenanceId);

        // The path id is the other rider's record. The query string names this rider's own
        // bike and maintenance id, which is the object an ownership check must not substitute.
        $client->request('GET', '/dashboard/maintenance/'.$this->victimMaintenanceId, [
            'bike' => $this->attackerBikeId,
            'id' => $this->attackerMaintenanceId,
        ]);

        self::assertResponseStatusCodeSame(
            Response::HTTP_NOT_FOUND,
            'A signed-in rider who supplies another rider\'s maintenance id must not receive that service record.'
        );
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('maintenance_show', $content);
        self::assertStringNotContainsString(self::VICTIM_BIKE, $content);
        self::assertStringNotContainsString(self::VICTIM_WORKSHOP, $content);
        self::assertStringNotContainsString(self::VICTIM_TASK, $content);
        self::assertStringNotContainsString('654,321', $content);
        self::assertStringNotContainsString((string) self::VICTIM_ODOMETER, $content);
        self::assertSame($before, $this->maintenanceSnapshot($this->victimMaintenanceId));
    }

    public function testAnonymousRequestDoesNotRenderMaintenanceDetails(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard/maintenance/'.$this->victimMaintenanceId);

        self::assertResponseRedirects();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString(self::VICTIM_BIKE, $content);
        self::assertStringNotContainsString(self::VICTIM_WORKSHOP, $content);
        self::assertStringNotContainsString(self::VICTIM_TASK, $content);
    }

    private function signIn(string $username): KernelBrowser
    {
        $client = static::createClient();
        $client->request('POST', '/login', [
            '_username' => $username,
            '_password' => self::PASSWORD,
        ]);

        self::assertResponseRedirects();

        return $client;
    }

    /**
     * @return array{victimMaintenanceId: string, attackerMaintenanceId: string, attackerBikeId: string}
     */
    private function seedTwoRiders(EntityManagerInterface $em): array
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $migrator = $this->migrator($em);

        $victim = $this->rider($hasher, $migrator, 'rider-north', 'North', 'Rider', 'north@example.test');
        $attacker = $this->rider($hasher, $migrator, 'rider-south', 'South', 'Rider', 'south@example.test');
        $em->persist($victim);
        $em->persist($attacker);
        $em->flush();

        $manufacturer = new Manufacturer($this->named(new CreateManufacturerCommand(), 'Sentinel Motor'), $migrator);
        $model = new Model($this->modelCommand(), $manufacturer, $migrator);
        $victimWorkshop = new Workshop($this->named(new CreateWorkshopCommand(), self::VICTIM_WORKSHOP), $victim);
        $attackerWorkshop = new Workshop($this->named(new CreateWorkshopCommand(), self::ATTACKER_WORKSHOP), $attacker);
        $victimTask = new Task($this->taskCommand(self::VICTIM_TASK), $victim);
        $attackerTask = new Task($this->taskCommand(self::ATTACKER_TASK), $attacker);
        $victimBike = new Bike($this->bikeCommand($model, self::VICTIM_BIKE), $victim);
        $attackerBike = new Bike($this->bikeCommand($model, self::ATTACKER_BIKE), $attacker);

        $victimMaintenance = $this->maintenance($victimBike, $victimWorkshop, $victimTask, $victim, self::VICTIM_ODOMETER, '80.00');
        $attackerMaintenance = $this->maintenance($attackerBike, $attackerWorkshop, $attackerTask, $attacker, self::ATTACKER_ODOMETER, '15.00');

        foreach ([
            $manufacturer, $model, $victimWorkshop, $attackerWorkshop, $victimTask, $attackerTask,
            $victimBike, $attackerBike, $victimMaintenance, $attackerMaintenance,
        ] as $entity) {
            $em->persist($entity);
        }
        foreach ($victimMaintenance->getMaintenanceTasks() as $task) {
            $em->persist($task);
        }
        foreach ($attackerMaintenance->getMaintenanceTasks() as $task) {
            $em->persist($task);
        }
        $em->flush();

        return [
            'victimMaintenanceId' => $victimMaintenance->getId()->toString(),
            'attackerMaintenanceId' => $attackerMaintenance->getId()->toString(),
            'attackerBikeId' => $attackerBike->getId()->toString(),
        ];
    }

    private function maintenance(Bike $bike, Workshop $workshop, Task $task, User $user, int $odometer, string $cost): Maintenance
    {
        $command = new CreateMaintenanceCommand();
        $command->workshop = $workshop;
        $command->bike = $bike;
        $command->date = new \DateTime('2024-06-15');
        $command->odometer = $odometer;
        $command->spentTime = '1.50';
        $command->unspecifiedCosts = '0.00';

        $maintenance = $bike->createMaintenance($command, $user);

        $taskCommand = new CreateMaintenanceTaskCommand();
        $taskCommand->task = $task;
        $taskCommand->cost = $cost;
        $taskCommand->comment = '';
        $maintenance->createMaintenanceTask($taskCommand, $user);

        return $maintenance;
    }

    private function rider(UserPasswordHasherInterface $hasher, User $migrator, string $username, string $firstName, string $lastName, string $email): User
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = $firstName;
        $command->lastName = $lastName;
        $command->email = $email;
        $command->mobile = '000';
        $command->isRoleAdmin = false;
        $command->password = self::PASSWORD;
        $command->profilePictureFilename = '';

        return new User($command, $migrator, $hasher);
    }

    private function migrator(EntityManagerInterface $em): User
    {
        $user = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $id = Uuid::fromString('00000000-0000-4000-8000-000000000001');
        $this->write($user, 'id', $id);
        $this->write($user, 'createdBy', $user);
        $this->write($user, 'createdOn', new \DateTimeImmutable('2020-01-01 00:00:00'));
        $this->write($user, 'username', 'db-migrator');
        $this->write($user, 'firstName', 'Database');
        $this->write($user, 'lastName', 'Migrator');
        $this->write($user, 'password', '');
        $this->write($user, 'roles', ['ROLE_USER']);
        $this->write($user, 'email', 'migrator@example.test');
        $this->write($user, 'mobile', null);
        $this->write($user, 'isActive', false);
        $this->write($user, 'profilePictureFilename', '');
        $this->write($user, 'bikes', new ArrayCollection());
        $this->write($user, 'workshops', new ArrayCollection());
        $this->write($user, 'ownedWorkshops', new ArrayCollection());
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function bikeCommand(Model $model, string $nickname): CreateBikeCommand
    {
        $command = new CreateBikeCommand();
        $command->model = $model;
        $command->nickname = $nickname;
        $command->purchasePrice = '1000.00';
        $command->purchaseOdometer = 0;
        $command->purchaseDate = new \DateTime('2024-01-01');
        $command->year = 2024;
        $command->vin = 'SENTINELVIN0000001';
        $command->pictureFilename = '';
        $command->fuelTanksize = 15;

        return $command;
    }

    private function modelCommand(): CreateModelCommand
    {
        $command = new CreateModelCommand();
        $command->name = 'Sentinel';
        $command->alterName = 'Trail';
        $command->displacement = 250;
        $command->yearFrom = 2020;
        $command->yearTo = 2026;
        $command->vinRanges = [];
        $command->pictureFilename = '';
        $command->fuelTankSize = 15;

        return $command;
    }

    private function taskCommand(string $name): CreateTaskCommand
    {
        $command = new CreateTaskCommand();
        $command->name = $name;
        $command->description = $name;
        $command->part = null;
        $command->comment = '';

        return $command;
    }

    private function named(object $command, string $name): object
    {
        $command->name = $name;

        return $command;
    }

    private function write(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }

    private function rebuildSchema(EntityManagerInterface $em): void
    {
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    /**
     * @return array{odometer: int, workshop: string, bike: string, tasks: list<array{name: string, cost: string}>}
     */
    private function maintenanceSnapshot(string $id): array
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $maintenance = $em->find(Maintenance::class, $id);
        self::assertInstanceOf(Maintenance::class, $maintenance);

        $tasks = [];
        foreach ($maintenance->getMaintenanceTasks() as $task) {
            $tasks[] = [
                'name' => $task->getTask()->getName(),
                'cost' => (string) $task->getCost(),
            ];
        }

        return [
            'odometer' => $maintenance->getOdometer(),
            'workshop' => $maintenance->getWorkshop()->getName(),
            'bike' => $maintenance->getBike()->getName(),
            'tasks' => $tasks,
        ];
    }
}
