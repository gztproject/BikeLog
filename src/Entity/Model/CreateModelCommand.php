<?php

namespace App\Entity\Model;

class CreateModelCommand
{
	public $name;
	public $alterName;
	public $displacement;
	public $yearFrom;
	public $yearTo;
	public $vinRanges;
	public $pictureFilename;
	public $fuelTankSize;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
