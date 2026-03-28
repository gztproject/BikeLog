<?php

namespace App\Entity\MaintenanceTask;

class CreateMaintenanceTaskCommand
{	
	public $task;
	public $cost;
	public $comment;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
