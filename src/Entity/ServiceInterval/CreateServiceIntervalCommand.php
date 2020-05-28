<?php

namespace App\Entity\ServiceInterval;

class CreateServiceIntervalCommand
{
	public $bike;
	public $model;
	public $interval;
	public $task;
	
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
