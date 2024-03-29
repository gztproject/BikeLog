<?php

namespace App\Entity\MaintenanceTask;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBaseWithComment;
use App\Entity\User\User;
use App\Entity\Maintenance\Maintenance;
use App\Entity\Task\Task;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\MaintenanceTask\MaintenanceTaskRepository")
 */
class MaintenanceTask extends AggregateBaseWithComment {

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Maintenance\Maintenance", inversedBy="maintenanceTasks")
	 */
	private $maintenance;

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Task\Task")
	 */
	private $task;
			
	/**
	 *
	 * @ORM\Column(type="decimal", precision=5, scale=2)
	 */
	private $cost;

	/**
	 *
	 * @param CreateMaintenanceTaskCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateMaintenanceTaskCommand $c, Maintenance $maintenance, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->maintenance = $maintenance;
		$this->task = $c->task;
		$this->cost = $c->cost;
		$this->comment = $c->comment;
	}
	
	/**
	 * 
	 * @param UpdateMaintenanceTaskCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return MaintenanceTask
	 */
	public function update(UpdateMaintenanceTaskCommand $c, User $user): MaintenanceTask {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}
	
	/**
	 * 
	 * @return Maintenance
	 */
	public function getMaintenance(): Maintenance{
		return $this->maintenance;
	}
	
	/**
	 * 
	 * @return Task
	 */
	public function getTask(): Task{
		return $this->task;
	}
	
	public function getDescription(): string
	{
	    return $this->getTask()->getDescription();
	}
	
	/**
	 *
	 * @return float
	 */
	public function getCost(): float {
		return $this->cost;
	}	
}
