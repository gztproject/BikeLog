<?php

namespace App\Tests\Entity;

use App\Entity\Bike\Bike;
use App\Entity\Bike\CreateBikeCommand;
use App\Entity\Maintenance\CreateMaintenanceCommand;
use App\Entity\MaintenanceTask\CreateMaintenanceTaskCommand;
use App\Entity\Model\Model;
use App\Entity\Part\CreatePartCommand;
use App\Entity\Part\Part;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\Task\CreateTaskCommand;
use App\Entity\Task\Task;
use App\Entity\User\User;
use App\Entity\Workshop\Workshop;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class BikeStatisticsTest extends TestCase
{
    public function testAverageConsumptionIgnoresSmallOrPartialRefuelings(): void
    {
        $bike = $this->createBike();

        $bike->createRefueling($this->createRefuelingCommand('2026-01-01 08:00:00', 10000, 12.0, 18.0, true, true), $this->createUserMock());
        $bike->createRefueling($this->createRefuelingCommand('2026-01-08 08:00:00', 10200, 10.0, 15.0, true, true), $this->createUserMock());
        $bike->createRefueling($this->createRefuelingCommand('2026-01-09 08:00:00', 10220, 1.5, 2.4, true, true), $this->createUserMock());
        $bike->createRefueling($this->createRefuelingCommand('2026-01-10 08:00:00', 10320, 8.0, 12.0, false, true), $this->createUserMock());

        $stats = $bike->getRefuelingStatistics();

        self::assertSame(4.0, $bike->getNumberOfRefuelings());
        self::assertSame(1, $stats['qualifiedRefuelings']);
        self::assertSame(2, $stats['excludedRefuelings']);
        self::assertSame(5.0, $stats['averageConsumption']);
        self::assertSame(280.0, round($stats['averageRange'], 2));
        self::assertSame(3.0, $stats['minimumFuelQuantity']);
    }

    public function testServiceAlertsUsePurchaseBaselineWhenTaskWasNeverRecorded(): void
    {
        $task = $this->createTask('Brake fluid', 'Replace brake fluid');
        $bike = $this->createBike([], new \DateTime('2025-01-01'));
        $this->attachServiceInterval($bike, $task, 180, 20);

        $alerts = $bike->getServiceAlerts();

        self::assertCount(1, $alerts);
        self::assertSame('overdue', $alerts[0]['status']);
        self::assertTrue($alerts[0]['usesPurchaseBaseline']);
        self::assertFalse($alerts[0]['hasRecordedService']);
        self::assertSame('2025-06-30', $alerts[0]['dueDate']->format('Y-m-d'));
        self::assertSame(
            (new \DateTimeImmutable('today'))->diff($alerts[0]['dueDate'])->format('%r%a'),
            (string) $alerts[0]['remainingDays']
        );
    }

    public function testPartStatisticsGroupServiceHistoryAndPerformanceAgainstIntervals(): void
    {
        $bike = $this->createBike([], new \DateTime('2025-01-01'));
        $oilPart = $this->createPart('Oil system');
        $chainPart = $this->createPart('Chain drive');
        $oilTask = $this->createTask('Oil change', 'Replace oil and filter', $oilPart);
        $chainTask = $this->createTask('Chain adjustment', 'Adjust chain slack', $chainPart);

        $this->attachServiceInterval($bike, $oilTask, 5000, 10);
        $this->attachServiceInterval($bike, $chainTask, 1000, 10);

        $this->addMaintenance($bike, '2025-03-01', 14000, [[$chainTask, 15.0]]);
        $this->addMaintenance($bike, '2025-06-01', 15000, [[$oilTask, 75.0]]);
        $this->addMaintenance($bike, '2025-04-01', 14700, [[$chainTask, 15.0]]);
        $this->addMaintenance($bike, '2025-05-01', 15400, [[$chainTask, 15.0]]);
        $this->addMaintenance($bike, '2025-11-01', 19800, [[$oilTask, 82.0]]);

        $partStatistics = $bike->getPartStatistics();
        $oilStatistics = $this->findPartStatistics($partStatistics, 'Oil system');
        $chainStatistics = $this->findPartStatistics($partStatistics, 'Chain drive');

        self::assertSame(1, $oilStatistics['trackedTaskCount']);
        self::assertSame(2, $oilStatistics['servicesRecorded']);
        self::assertSame(4800.0, $oilStatistics['averageDistanceBetweenServices']);
        self::assertSame('on_target', $oilStatistics['taskStats'][0]['performanceKey']);
        self::assertSame(19800, $oilStatistics['lastServiceOdometer']);

        self::assertSame(1, $chainStatistics['trackedTaskCount']);
        self::assertSame(3, $chainStatistics['servicesRecorded']);
        self::assertSame(700.0, $chainStatistics['averageDistanceBetweenServices']);
        self::assertSame(1, $chainStatistics['underperformerCount']);
        self::assertSame('underperforming', $chainStatistics['taskStats'][0]['performanceKey']);
        self::assertSame('warning', $chainStatistics['status']);
        self::assertSame('overdue', $chainStatistics['taskStats'][0]['serviceStatus']['status']);
    }

    private function createBike(array $serviceIntervals = [], ?\DateTimeInterface $purchaseDate = null): Bike
    {
        $command = new CreateBikeCommand();
        $command->model = $this->createModelMock($serviceIntervals);
        $command->nickname = 'Workshop Mule';
        $command->purchasePrice = 9500;
        $command->purchaseOdometer = 9200;
        $command->purchaseDate = $purchaseDate ?? new \DateTime('2025-01-01');
        $command->year = 2024;
        $command->vin = 'VIN-123456789';
        $command->fuelTanksize = 14;
        $command->pictureFilename = '';

        return new Bike($command, $this->createUserMock());
    }

    private function createRefuelingCommand(
        string $datetime,
        int $odometer,
        float $fuelQuantity,
        float $price,
        bool $isTankFull,
        bool $isNotBreakingContinuum
    ): CreateRefuelingCommand {
        $command = new CreateRefuelingCommand();
        $command->datetime = new \DateTimeImmutable($datetime);
        $command->odometer = $odometer;
        $command->fuelQuantity = $fuelQuantity;
        $command->price = $price;
        $command->isTankFull = $isTankFull;
        $command->isNotBreakingContinuum = $isNotBreakingContinuum;
        $command->comment = '';
        $command->latitude = null;
        $command->longitude = null;

        return $command;
    }

    private function attachServiceInterval(Bike $bike, Task $task, int $interval, int $intervalType): void
    {
        $command = new CreateServiceIntervalCommand();
        $command->task = $task;
        $command->interval = $interval;
        $command->intervalType = $intervalType;

        $bike->createServiceInterval($command, $this->createUserMock());
    }

    private function createTask(string $name, string $description, ?Part $part = null): Task
    {
        $command = new CreateTaskCommand();
        $command->name = $name;
        $command->description = $description;
        $command->part = $part;
        $command->comment = '';

        return new Task($command, $this->createUserMock());
    }

    private function createPart(string $name): Part
    {
        $command = new CreatePartCommand();
        $command->name = $name;

        $manufacturer = $this->getMockBuilder(\App\Entity\Manufacturer\Manufacturer::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new Part($command, $manufacturer, $this->createUserMock());
    }

    private function addMaintenance(Bike $bike, string $date, int $odometer, array $tasks): void
    {
        $command = new CreateMaintenanceCommand();
        $command->bike = $bike;
        $command->workshop = $this->createWorkshopMock();
        $command->date = new \DateTimeImmutable($date);
        $command->odometer = $odometer;
        $command->spentTime = 1.0;
        $command->unspecifiedCosts = 0.0;

        $maintenance = $bike->createMaintenance($command, $this->createUserMock());

        foreach ($tasks as [$task, $cost]) {
            $taskCommand = new CreateMaintenanceTaskCommand();
            $taskCommand->task = $task;
            $taskCommand->cost = $cost;
            $taskCommand->comment = '';

            $maintenance->createMaintenanceTask($taskCommand, $this->createUserMock());
        }
    }

    private function findPartStatistics(array $partStatistics, string $partName): array
    {
        foreach ($partStatistics as $partStatistic) {
            if ($partStatistic['name'] === $partName) {
                return $partStatistic;
            }
        }

        self::fail(sprintf('Unable to find part statistics for "%s".', $partName));
    }

    private function createModelMock(array $serviceIntervals = []): Model
    {
        $model = $this->getMockBuilder(Model::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getServiceIntervals', 'getFuelTankSize', 'getName', 'getAlterName', 'getPictureFilename'])
            ->getMock();

        $model->method('getServiceIntervals')->willReturn(new ArrayCollection($serviceIntervals));
        $model->method('getFuelTankSize')->willReturn(14.0);
        $model->method('getName')->willReturn('Test');
        $model->method('getAlterName')->willReturn('Bike');
        $model->method('getPictureFilename')->willReturn('img/No_motorcycle.png');

        return $model;
    }

    private function createUserMock(): User
    {
        return $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createWorkshopMock(): Workshop
    {
        $workshop = $this->getMockBuilder(Workshop::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName'])
            ->getMock();

        $workshop->method('getName')->willReturn('Workshop');

        return $workshop;
    }
}
