<?php

namespace App\Entity\Maintenance;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Maintenance\MaintenanceRepository")
 */
class Maintenance extends AggregateBase {
	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $name;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\MaintenanceTask\MaintenanceTask", mappedBy="maintenance")
	 */
	private $maintenanceTasks;

	
	/**
	 * 
	 * @param CreateMaintenanceCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateMaintenanceCommand $c, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->name = $c->name;
		$this->owner = $c->owner;
	}
	public function update(UpdateMaintenanceCommand $c, User $user): Maintenance {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}
	public function getName(): ?string {
		return $this->name;
	}
	
}
