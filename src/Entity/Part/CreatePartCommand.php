<?php

namespace App\Entity\Part;

class CreatePartCommand
{	
	public $name;
		
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
