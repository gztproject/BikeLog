<?php

namespace App\Entity\MaintenanceTask;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CreateMaintenanceTaskCommand
{	
	public $task;
	public $cost;
	public $comment;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}

	public static function loadValidatorMetadata(ClassMetadata $metadata): void
	{
		$metadata->addPropertyConstraint('cost', new Assert\GreaterThanOrEqual([
			'value' => 0,
			'message' => 'maintenance.task_cost_non_negative',
		]));
	}
}
