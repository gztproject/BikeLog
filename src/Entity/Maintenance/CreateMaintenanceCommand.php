<?php

namespace App\Entity\Maintenance;

use App\Entity\MaintenanceTask\CreateMaintenanceTaskCommand;

class CreateMaintenanceCommand
{
	public $workshop;
	public $bike;
	public $date;
	public $odometer;
	public $spentTime;
	public $unspecifiedCost;
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
}
