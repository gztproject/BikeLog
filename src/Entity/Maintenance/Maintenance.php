<?php

namespace App\Entity\Maintenance;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;
use App\Entity\Workshop\Workshop;
use App\Entity\Bike\Bike;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Maintenance\MaintenanceRepository")
 */
class Maintenance extends AggregateBase {

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Workshop\Workshop", inversedBy="maintenances")
	 */
	private $workshop;

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Bike\Bike", inversedBy="maintenances")
	 */
	private $bike;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\MaintenanceTask\MaintenanceTask", mappedBy="maintenance")
	 */
	private $maintenanceTasks;

	/**
	 *
	 * @ORM\Column(type="date")
	 */
	private $date;

	/**
	 *
	 * @ORM\Column(type="integer")
	 */
	private $odometer;

	/**
	 *
	 * @ORM\Column(type="decimal", precision=5, scale=2)
	 */
	private $spentTime;

	/**
	 *
	 * @ORM\Column(type="decimal", precision=5, scale=2)
	 */
	private $unspecifiedCosts;
	
	/**
	 *
	 * @ORM\Column(type="string", length=2048)
	 */
	private $comment;

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

	/**
	 *
	 * @param UpdateMaintenanceCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return Maintenance
	 */
	public function update(UpdateMaintenanceCommand $c, User $user): Maintenance {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}

	/**
	 *
	 * @return Workshop
	 */
	public function getWorkshop(): Workshop {
		return $this->workshop;
	}

	/**
	 *
	 * @return Bike
	 */
	public function getBike(): Bike {
		return $this->bike;
	}

	/**
	 *
	 * @return Collection
	 */
	public function getMaintenanceTasks(): Collection {
		return $this->maintenanceTasks;
	}

	/**
	 *
	 * @return \DateTimeInterface
	 */
	public function getDate(): \DateTimeInterface {
		return $this->date;
	}

	/**
	 *
	 * @return string
	 */
	public function getDateString(): string {
		return $this->date->format ( 'j. n. Y' );
	}

	/**
	 *
	 * @return int
	 */
	public function getOdometer(): int {
		return $this->odometer;
	}

	/**
	 *
	 * @return float
	 */
	public function getSpentTime(): float {
		return $this->spentTime;
	}

	/**
	 *
	 * @return float
	 */
	public function getUnspecifiedCosts(): float {
		return $this->unspecifiedCosts;
	}
	
	/**
	 *
	 * @return string
	 */
	public function getComment(): string{
		return $this->comment;
	}
}
