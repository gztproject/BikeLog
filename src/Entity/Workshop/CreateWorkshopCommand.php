<?php

namespace App\Entity\Workshop;

class CreateWorkshopCommand
{
	public $owner;
	public $name;	
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
