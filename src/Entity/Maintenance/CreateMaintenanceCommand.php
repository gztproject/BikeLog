<?php

namespace App\Entity\Maintenance;

class CreateMaintenanceCommand
{
	public $workshop;
	public $bike;
	public $date;
	public $odometer;
	public $spentTime;
	public $unspecifiedCost;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
