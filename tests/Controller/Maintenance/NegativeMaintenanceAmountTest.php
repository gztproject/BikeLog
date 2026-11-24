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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * A workshop client must not be able to store negative labor hours, extra costs,
 * or task costs on a maintenance entry for a bike they own.
 *
 * These tests fail while that input is accepted and stored.
 */
class NegativeMaintenanceAmountTest extends WebTestCase
{
    private const DATABASE_FILE = '/tmp/bikelog-negative-maintenance.sqlite';

    private KernelBrowser $client;

    private string $riderId;

    private string $ownerId;

    private string $bikeId;

    private string $workshopId;

    private string $taskId;

    public static function setUpBeforeClass(): void
    {
        $databaseUrl = 'sqlite:///' . self::DATABASE_FILE;
        putenv('DATABASE_URL=' . $databaseUrl);
        $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['DATABASE_URL'] = $databaseUrl;
    }

    protected function setUp(): void
    {
        if (is_file(self::DATABASE_FILE)) {
            unlink(self::DATABASE_FILE);
        }

        $this->client = static::createClient();
        $this->seedWorkshopClient();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_file(self::DATABASE_FILE)) {
            unlink(self::DATABASE_FILE);
        }
    }

    public function testZeroAndPositiveAmountsAreStoredForTheAuthorizedWorkshop(): void
    {
        $this->loginRider();
        $this->postMaintenance($this->amounts('2.00', '10.00', '5.00', '01. 01. 2026', '1500'));

        self::assertTrue(
            $this->client->getResponse()->isRedirect(),
            'A non-negative maintenance entry should be accepted. ' . $this->responseExcerpt()
        );

        $state = $this->reloadState();
        self::assertCount(1, $state['maintenances']);
        self::assertSame($this->workshopId, $state['maintenances'][0]->getWorkshop()->getId()->toString());
        self::assertSame($this->bikeId, $state['maintenances'][0]->getBike()->getId()->toString());
        self::assertEqualsWithDelta(2.0, $state['workTime'], 0.001);
        self::assertEqualsWithDelta(15.0, $state['maintenances'][0]->getTotalCost(), 0.001);
        self::assertGreaterThanOrEqual(0.0, $state['maintenances'][0]->getSpentTime());
        self::assertGreaterThanOrEqual(0.0, $state['maintenances'][0]->getUnspecifiedCosts());

        $this->loginOwner();
        $this->client->request('GET', '/dashboard/workshop/' . $this->workshopId);
        self::assertResponseIsSuccessful();
        $ownerPage = $this->client->getResponse()->getContent();
        self::assertStringContainsString('1. 1. 2026', $ownerPage);
        self::assertStringContainsString('Lubricate the chain', $ownerPage);
        self::assertMatchesRegularExpression('/Skupen čas dela(?:(?!<\/section>).)*?2(?:\.0+)?\s*h/su', $ownerPage);

        $this->loginRider();
        $this->client->request('GET', '/dashboard/maintenance');
        self::assertResponseIsSuccessful();
        $maintenanceLog = $this->client->getResponse()->getContent();
        self::assertStringContainsString('2.0', $maintenanceLog);
        self::assertStringContainsString('15.00', $maintenanceLog);

        $this->postMaintenance($this->amounts('0', '0', '0', '03. 01. 2026', '1600'));
        self::assertTrue(
            $this->client->getResponse()->isRedirect(),
            'Zero hours and costs are non-negative and should be accepted. ' . $this->responseExcerpt()
        );
        $withZero = $this->reloadState();
        self::assertCount(2, $withZero['maintenances']);
        self::assertEqualsWithDelta(2.0, $withZero['workTime'], 0.001);
    }

    /**
     * @dataProvider negativeFieldProvider
     */
    public function testDirectPostRejectsEachNegativeAmount(string $field, string $negativeValue): void
    {
        $this->loginRider();
        $this->postMaintenance($this->amounts('2.00', '10.00', '5.00', '01. 01. 2026', '1500'));
        self::assertTrue($this->client->getResponse()->isRedirect(), 'Baseline entry was not accepted. ' . $this->responseExcerpt());

        $negative = $this->amounts('2.00', '10.00', '5.00', '02. 01. 2026', '1800');
        $negative[$field] = $negativeValue;
        $this->postMaintenance($negative);

        $state = $this->reloadState();
        $problems = $this->negativeStorageProblems($state, $negativeValue);

        self::assertSame(
            [],
            $problems,
            "Posting {$field}={$negativeValue} on the maintenance form must be rejected before it is stored.\n"
            . implode("\n", $problems)
        );
    }

    public function testDirectPostOfCombinedNegativeAmountsDoesNotChangeTheWorkshopHistory(): void
    {
        $this->loginRider();
        $this->postMaintenance($this->amounts('2.00', '10.00', '5.00', '01. 01. 2026', '1500'));
        self::assertTrue($this->client->getResponse()->isRedirect(), 'Baseline entry was not accepted. ' . $this->responseExcerpt());

        $this->postMaintenance($this->amounts('-4.50', '-12.50', '-8.25', '02. 01. 2026', '1800'));

        $state = $this->reloadState();
        $problems = $this->negativeStorageProblems($state, '-4.50');

        $this->loginOwner();
        $this->client->request('GET', '/dashboard/workshop/' . $this->workshopId);
        $ownerPage = (string) $this->client->getResponse()->getContent();
        if (str_contains($ownerPage, '2. 1. 2026')) {
            $problems[] = 'The workshop owner service history lists the negative entry.';
        }
        if (preg_match('/Skupen čas dela(?:(?!<\/section>).)*?-\d/su', $ownerPage)) {
            $problems[] = 'The workshop owner page shows a negative total work time.';
        }

        foreach ($state['maintenances'] as $maintenance) {
            $this->client->request('GET', '/dashboard/maintenance/' . $maintenance->getId()->toString());
            $ownerDetail = (string) $this->client->getResponse()->getContent();
            if (preg_match('/-\d[\d\s.,]*€/u', $ownerDetail)) {
                $problems[] = 'The workshop owner can open the maintenance detail and see a negative task cost.';
            }
        }

        $this->loginRider();
        $this->client->request('GET', '/dashboard/maintenance');
        $maintenanceLog = (string) $this->client->getResponse()->getContent();
        foreach (['-4.5', '-20.75', '-8.25'] as $negativeText) {
            if (str_contains($maintenanceLog, $negativeText)) {
                $problems[] = 'The maintenance log renders ' . $negativeText . '.';
            }
        }

        self::assertSame(
            [],
            $problems,
            "A client POST with negative hours and costs must not change the workshop total or service history.\n"
            . implode("\n", $problems)
        );
    }

    /**
     * @dataProvider negativeCommandProvider
     */
    public function testMaintenanceEntityRefusesNegativeAmountsBeforeStorage(string $spentTime, string $extraCost, string $taskCost): void
    {
        $em = $this->entityManager();
        $this->storeNonNegativeMaintenance($em, '3.00', '1.00', '1.00', '2026-03-01', 3000);

        $before = $this->reloadState();
        $bike = $em->find(Bike::class, $this->bikeId);
        $workshop = $em->find(Workshop::class, $this->workshopId);
        $rider = $em->find(User::class, $this->riderId);
        $task = $em->find(Task::class, $this->taskId);

        $command = new CreateMaintenanceCommand();
        $command->workshop = $workshop;
        $command->bike = $bike;
        $command->date = new \DateTimeImmutable('2026-03-02');
        $command->odometer = 3200;
        $command->spentTime = (float) $spentTime;
        $command->unspecifiedCosts = (float) $extraCost;

        $storedNegative = false;
        try {
            $maintenance = $bike->createMaintenance($command, $rider);
            $taskCommand = new CreateMaintenanceTaskCommand();
            $taskCommand->task = $task;
            $taskCommand->cost = (float) $taskCost;
            $taskCommand->comment = '';
            $maintenance->createMaintenanceTask($taskCommand, $rider);
            $em->persist($maintenance);
            foreach ($maintenance->getMaintenanceTasks() as $maintenanceTask) {
                $em->persist($maintenanceTask);
            }
            $em->persist($bike);
            $em->flush();
            $storedNegative = true;
        } catch (\Throwable $exception) {
            $storedNegative = false;
        }

        $after = $this->reloadState();
        $problems = [];
        if ($storedNegative) {
            $problems[] = 'createMaintenance stored the command instead of refusing it.';
        }
        if (abs($after['workTime'] - $before['workTime']) > 0.001) {
            $problems[] = 'Workshop work time changed from ' . $before['workTime'] . ' to ' . $after['workTime'] . '.';
        }
        if (count($after['maintenances']) !== count($before['maintenances'])) {
            $problems[] = 'Maintenance count changed from ' . count($before['maintenances']) . ' to ' . count($after['maintenances']) . '.';
        }
        foreach ($after['maintenances'] as $maintenance) {
            if ((float) $maintenance->getSpentTime() < 0 || (float) $maintenance->getUnspecifiedCosts() < 0 || (float) $maintenance->getTotalCost() < 0) {
                $problems[] = sprintf(
                    'Stored maintenance spentTime=%s unspecifiedCosts=%s totalCost=%s.',
                    $maintenance->getSpentTime(),
                    $maintenance->getUnspecifiedCosts(),
                    $maintenance->getTotalCost()
                );
            }
            foreach ($maintenance->getMaintenanceTasks() as $maintenanceTask) {
                if ((float) $maintenanceTask->getCost() < 0) {
                    $problems[] = 'Stored task cost ' . $maintenanceTask->getCost() . '.';
                }
            }
        }

        self::assertSame(
            [],
            $problems,
            "The maintenance entity must refuse negative hours and costs before they are stored.\n" . implode("\n", $problems)
        );
    }

    public function negativeFieldProvider(): array
    {
        return [
            'labor hours' => ['spentTime', '-4.50'],
            'extra costs' => ['unspecifiedCosts', '-12.50'],
            'task cost' => ['taskCost', '-8.25'],
        ];
    }

    public function negativeCommandProvider(): array
    {
        return [
            'labor hours' => ['-4.50', '0.00', '0.00'],
            'extra costs' => ['0.00', '-12.50', '0.00'],
            'task cost' => ['0.00', '0.00', '-8.25'],
        ];
    }

    private function amounts(string $spentTime, string $extraCost, string $taskCost, string $date, string $odometer): array
    {
        return [
            'spentTime' => $spentTime,
            'unspecifiedCosts' => $extraCost,
            'taskCost' => $taskCost,
            'date' => $date,
            'odometer' => $odometer,
        ];
    }

    private function negativeStorageProblems(array $state, string $negativeValue): array
    {
        $problems = [];
        if (count($state['maintenances']) !== 1 && $this->client->getResponse()->isRedirect()) {
            $problems[] = 'The form accepted the POST and redirected to ' . $this->client->getResponse()->headers->get('Location') . '.';
        }
        if (abs($state['workTime'] - 2.0) > 0.001) {
            $problems[] = 'Workshop work time is ' . $state['workTime'] . ' instead of 2.';
        }
        if (count($state['maintenances']) !== 1) {
            $problems[] = 'Stored maintenance count is ' . count($state['maintenances']) . ' instead of 1.';
        }
        foreach ($state['maintenances'] as $maintenance) {
            if ((float) $maintenance->getSpentTime() < 0 || (float) $maintenance->getUnspecifiedCosts() < 0 || (float) $maintenance->getTotalCost() < 0) {
                $problems[] = sprintf(
                    'Stored maintenance spentTime=%s unspecifiedCosts=%s totalCost=%s (posted %s).',
                    $maintenance->getSpentTime(),
                    $maintenance->getUnspecifiedCosts(),
                    $maintenance->getTotalCost(),
                    $negativeValue
                );
            }
            foreach ($maintenance->getMaintenanceTasks() as $maintenanceTask) {
                if ((float) $maintenanceTask->getCost() < 0) {
                    $problems[] = 'Stored task cost ' . $maintenanceTask->getCost() . '.';
                }
            }
        }
        if ($state['maintenances'] !== []) {
            $sample = $state['maintenances'][0];
            $history = $sample->getBike()->getFilteredMaintenances($sample->getWorkshop());
            if ($history->count() !== 1) {
                $problems[] = 'Workshop service history contains ' . $history->count() . ' entries.';
            }
        }

        return $problems;
    }

    private function postMaintenance(array $amounts): void
    {
        $crawler = $this->client->request('GET', '/dashboard/maintenance/new/' . $this->bikeId);
        $status = $this->client->getResponse()->getStatusCode();
        if (200 !== $status) {
            self::fail('The maintenance form did not render (' . $status . '). ' . $this->responseExcerpt());
        }

        $form = $crawler->filter('form[name="maintenance"]')->form();
        $values = $form->getPhpValues();
        $values['maintenance']['workshop'] = $this->workshopId;
        $values['maintenance']['bike'] = $this->bikeId;
        $values['maintenance']['date'] = $amounts['date'];
        $values['maintenance']['odometer'] = $amounts['odometer'];
        $values['maintenance']['spentTime'] = $amounts['spentTime'];
        $values['maintenance']['unspecifiedCosts'] = $amounts['unspecifiedCosts'];
        $values['maintenance']['maintenanceTaskCommands'] = [
            [
                'task' => $this->taskId,
                'cost' => $amounts['taskCost'],
            ],
        ];

        $this->client->request($form->getMethod(), $form->getUri(), $values);
    }

    private function storeNonNegativeMaintenance(
        EntityManagerInterface $em,
        string $spentTime,
        string $extraCost,
        string $taskCost,
        string $date,
        int $odometer
    ): void {
        $bike = $em->find(Bike::class, $this->bikeId);
        $workshop = $em->find(Workshop::class, $this->workshopId);
        $rider = $em->find(User::class, $this->riderId);
        $task = $em->find(Task::class, $this->taskId);

        $command = new CreateMaintenanceCommand();
        $command->workshop = $workshop;
        $command->bike = $bike;
        $command->date = new \DateTimeImmutable($date);
        $command->odometer = $odometer;
        $command->spentTime = (float) $spentTime;
        $command->unspecifiedCosts = (float) $extraCost;

        $maintenance = $bike->createMaintenance($command, $rider);
        $taskCommand = new CreateMaintenanceTaskCommand();
        $taskCommand->task = $task;
        $taskCommand->cost = (float) $taskCost;
        $taskCommand->comment = '';
        $maintenanceTask = $maintenance->createMaintenanceTask($taskCommand, $rider);

        $em->persist($maintenance);
        $em->persist($maintenanceTask);
        $em->persist($bike);
        $em->flush();
        $em->clear();
    }

    /**
     * @return array{workTime: float, maintenances: Maintenance[]}
     */
    private function reloadState(): array
    {
        $em = $this->entityManager();
        $em->clear();

        $workshop = $em->find(Workshop::class, $this->workshopId);
        $maintenances = $em->getRepository(Maintenance::class)->findAll();

        return [
            'workTime' => (float) $workshop->getWorkTime(),
            'maintenances' => $maintenances,
        ];
    }

    private function seedWorkshopClient(): void
    {
        $em = $this->entityManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($metadata);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $owner = $this->createUser($hasher, 'workshop-owner');
        $rider = $this->createUser($hasher, 'workshop-client', $owner);

        $manufacturerCommand = new CreateManufacturerCommand();
        $manufacturerCommand->name = 'Test Maker';
        $manufacturer = new Manufacturer($manufacturerCommand, $owner);

        $modelCommand = new CreateModelCommand();
        $modelCommand->name = 'Model';
        $modelCommand->alterName = 'Alt';
        $modelCommand->displacement = 500;
        $modelCommand->yearFrom = 2020;
        $modelCommand->yearTo = 2026;
        $modelCommand->vinRanges = [];
        $modelCommand->fuelTankSize = 14;
        $modelCommand->pictureFilename = '';
        $model = new Model($modelCommand, $manufacturer, $owner);

        $taskCommand = new CreateTaskCommand();
        $taskCommand->name = 'Chain lube';
        $taskCommand->description = 'Lubricate the chain';
        $taskCommand->part = null;
        $taskCommand->comment = '';
        $task = new Task($taskCommand, $owner);

        $bikeCommand = new CreateBikeCommand();
        $bikeCommand->model = $model;
        $bikeCommand->nickname = 'Rider Bike';
        $bikeCommand->purchasePrice = 1000;
        $bikeCommand->purchaseOdometer = 100;
        $bikeCommand->purchaseDate = new \DateTimeImmutable('2024-01-01');
        $bikeCommand->year = 2024;
        $bikeCommand->vin = 'VINTEST123';
        $bikeCommand->fuelTanksize = 14;
        $bikeCommand->pictureFilename = '';
        $bike = $rider->createBike($bikeCommand);

        $workshopCommand = new CreateWorkshopCommand();
        $workshopCommand->name = 'Corner Workshop';
        $workshop = $owner->createWorkshop($workshopCommand);
        $workshop->addClient($rider, $owner);

        $em->persist($owner);
        $em->persist($rider);
        $em->persist($manufacturer);
        $em->persist($model);
        $em->persist($task);
        $em->persist($workshop);
        $em->persist($bike);
        $em->flush();

        $this->ownerId = $owner->getId()->toString();
        $this->riderId = $rider->getId()->toString();
        $this->bikeId = $bike->getId()->toString();
        $this->workshopId = $workshop->getId()->toString();
        $this->taskId = $task->getId()->toString();

        $em->clear();
    }

    private function createUser(UserPasswordHasherInterface $hasher, string $username, ?User $creator = null): User
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = 'Test';
        $command->lastName = 'User';
        $command->email = $username . '@example.test';
        $command->mobile = '000';
        $command->isRoleAdmin = false;
        $command->password = 'placeholder-password';
        $command->profilePictureFilename = '';

        if ($creator === null) {
            $placeholder = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
            $idProperty = new \ReflectionProperty(\App\Entity\Base\AggregateBase::class, 'id');
            $idProperty->setValue($placeholder, Uuid::uuid6());
            $user = new User($command, $placeholder, $hasher);
            $createdBy = new \ReflectionProperty(\App\Entity\Base\Base::class, 'createdBy');
            $createdBy->setValue($user, $user);

            return $user;
        }

        return new User($command, $creator, $hasher);
    }

    private function loginRider(): void
    {
        $this->login($this->riderId);
    }

    private function loginOwner(): void
    {
        $this->login($this->ownerId);
    }

    private function login(string $userId): void
    {
        $user = $this->entityManager()->find(User::class, $userId);
        $this->client->loginUser($user);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    private function responseExcerpt(): string
    {
        return substr((string) $this->client->getResponse()->getContent(), 0, 2500);
    }
}
