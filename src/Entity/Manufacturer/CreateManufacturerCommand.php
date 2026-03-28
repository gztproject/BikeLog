<?php

namespace App\Entity\Manufacturer;

class CreateManufacturerCommand
{
	public $name;	
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
