<?php

namespace App\Entity\MaintenanceTask;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\MaintenanceTask\MaintenanceTaskRepository")
 */
class MaintenanceTask extends AggregateBase {
	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $name;

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
	 * @param CreateMaintenanceTaskCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateMaintenanceTaskCommand $c, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->name = $c->name;
		$this->owner = $c->owner;
	}
	public function update(UpdateMaintenanceTaskCommand $c, User $user): MaintenanceTask {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}
	public function getName(): ?string {
		return $this->name;
	}	
}
