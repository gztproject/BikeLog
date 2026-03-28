<?php

namespace App\Tests\Entity;

use App\Entity\Bike\Bike;
use App\Entity\Bike\CreateBikeCommand;
use App\Entity\Model\Model;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\Task\CreateTaskCommand;
use App\Entity\Task\Task;
use App\Entity\User\User;
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

    private function createTask(string $name, string $description): Task
    {
        $command = new CreateTaskCommand();
        $command->name = $name;
        $command->description = $description;
        $command->part = null;
        $command->comment = '';

        return new Task($command, $this->createUserMock());
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
}
