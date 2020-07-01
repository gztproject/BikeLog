<?php

namespace App\Entity\Refueling;

class CreateRefuelingCommand
{		
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
