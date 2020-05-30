<?php

namespace App\Entity\Bike;

class CreateBikeCommand
{
	public $owner;
	public $model;
	public $nickname;
	public $purchasePrice;
	public $year;
	public $vin;
	
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
