<?php

namespace App\Entity\Refueling;

class CreateRefuelingCommand
{	
	public $datetime;
	public $odometer;
	public $fuelQuantity;
	public $price;
	public $bike;
	public $isTankFull;
	public $isNotBreakingContinuum;
	public $comment;
	public $latitude;
	public $longitude;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
