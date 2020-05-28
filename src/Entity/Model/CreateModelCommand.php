<?php

namespace App\Entity\Model;

class CreateModelCommand
{
	public $manufacturer;
	public $name;
	public $alter_name;
	public $displacement;
	
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
