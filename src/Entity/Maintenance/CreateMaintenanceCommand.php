<?php

namespace App\Entity\Maintenance;

use App\Entity\MaintenanceTask\CreateMaintenanceTaskCommand;
use Symfony\Component\Validator\Constraints as Assert;
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
}
