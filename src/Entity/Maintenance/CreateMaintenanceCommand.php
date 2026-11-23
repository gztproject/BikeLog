<?php

namespace App\Entity\Maintenance;

use App\Entity\MaintenanceTask\CreateMaintenanceTaskCommand;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CreateMaintenanceCommand
{
	public $workshop;
	public $bike;
	public $date;
	public $odometer;
	public $spentTime;
	public $unspecifiedCosts;
	/**
	 * Need this for getting them from the form to controller...
	 * @var Array[CreateMaintenanceTaskCommand]
	 */
	public $maintenanceTaskCommands;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}

	public static function loadValidatorMetadata(ClassMetadata $metadata): void
	{
		$metadata->addPropertyConstraint('date', new Assert\NotNull([
			'message' => 'maintenance.date_required',
		]));
		$metadata->addPropertyConstraint('bike', new Assert\NotNull([
			'message' => 'maintenance.bike_required',
		]));
		$metadata->addPropertyConstraint('odometer', new Assert\NotNull([
			'message' => 'maintenance.odometer_required',
		]));
		$metadata->addPropertyConstraint('odometer', new Assert\Type([
			'type' => 'numeric',
			'message' => 'maintenance.odometer_numeric',
		]));
		$metadata->addPropertyConstraint('odometer', new Assert\GreaterThanOrEqual([
			'value' => 0,
			'message' => 'maintenance.odometer_non_negative',
		]));
		$metadata->addConstraint(new Assert\Callback('validateOdometer'));
		$metadata->addPropertyConstraint('spentTime', new Assert\GreaterThanOrEqual([
			'value' => 0,
			'message' => 'maintenance.spent_time_non_negative',
		]));
		$metadata->addPropertyConstraint('unspecifiedCosts', new Assert\GreaterThanOrEqual([
			'value' => 0,
			'message' => 'maintenance.unspecified_costs_non_negative',
		]));
		$metadata->addPropertyConstraint('maintenanceTaskCommands', new Assert\Valid());
	}

	public function validateOdometer(ExecutionContextInterface $context): void
	{
		if ($this->bike == null || ! is_numeric($this->odometer) || ! $this->date instanceof DateTimeInterface) {
			return;
		}

		$minimumOdometer = $this->bike->getHighestOdometerUntil($this->date);
		if ((int) $this->odometer >= $minimumOdometer) {
			return;
		}

		$context->buildViolation('maintenance.odometer_below_recorded')
			->setParameter('%minimum%', (string) $minimumOdometer)
			->atPath('odometer')
			->addViolation();
	}
}
