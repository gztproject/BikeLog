<?php

namespace App\Entity\Task;

class CreateTaskCommand
{
	public $description;
	public $part;
	public $name;
	public $comment;
	
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
