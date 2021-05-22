<?php

namespace App\Entity\Bike;

class CreateBikeCommand
{
	public $model;
	public $nickname;
	public $purchasePrice;
	public $purchaseOdometer;
	public $purchaseDate;
	public $year;
	public $vin;
	public $pictureFilename;
	public $fuelTanksize;
	
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
