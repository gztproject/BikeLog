<?php

namespace App\Entity\Bike;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Maintenance\CreateMaintenanceCommand;
use App\Entity\Maintenance\Maintenance;
use App\Entity\Model\Model;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use App\Entity\Part\CreatePartCommand;
use App\Entity\Part\Part;
use App\Entity\Part\iHasParts;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Entity\Refueling\Refueling;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\ServiceInterval\ServiceInterval;
use App\Entity\ServiceInterval\iHasServiceIntervals;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Bike\BikeRepository")
 */
class Bike extends AggregateBase implements iHasServiceIntervals {

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\User\User", inversedBy="bikes")
	 */
	private $owner;

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Model\Model")
	 */
	private $model;

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $nickname;

	/**
	 *
	 * @ORM\Column(type="decimal", precision=5, scale=2)
	 */
	private $purchasePrice;

	/**
	 *
	 * @ORM\Column(type="integer")
	 */
	private $year;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\ServiceInterval\ServiceInterval", mappedBy="bike")
	 */
	private $customServiceIntervals;

	/**
	 *
	 * @ORM\Column(type="string", length=50)
	 */
	private $vin;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\Maintenance\Maintenance", mappedBy="bike")
	 */
	private $maintenances;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\Refueling\Refueling", mappedBy="bike")
	 */
	private $refuelings;
	
	/**
	 *
	 * @ORM\OneToOne(targetEntity="App\Entity\Refueling\Refueling", nullable=true)
	 */
	private $lastRefueling;

// 	/**
// 	 *
// 	 * @ORM\ManyToMany(targetEntity="App\Entity\Part\Part", inversedBy="bikes")
// 	 */
// 	private $parts;

	/**
	 *
	 * @param CreateBikeCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateBikeCommand $c, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->nickname = $c->nickname;
		$this->model = $c->model;
		$this->owner = $c->owner;
		$this->purchasePrice = $c->purchasePrice;
		$this->year = $c->year;
		$this->customServiceIntervals = new ArrayCollection ();
		$this->vin = $c->vin;

		$this->maintenances = new ArrayCollection ();
		$this->refuelings = new ArrayCollection ();
	}

	/**
	 *
	 * @param UpdateBikeCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return Bike
	 */
	public function update(UpdateBikeCommand $c, User $user): Bike {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}

	/**
	 * Creates a CUSTOM(!) ServiceInterval or overrides it.
	 *
	 * @param CreateServiceIntervalCommand $c
	 * @param User $user
	 * @return ServiceInterval
	 */
	public function createServiceInterval(CreateServiceIntervalCommand $c, User $user): ServiceInterval {
		$csi = new ServiceInterval ( $c, $this, $user );
		$this->customServiceIntervals->add ( $csi );
		return $csi;
	}

	/**
	 *
	 * @param CreateRefuelingCommand $c
	 * @param User $user
	 * @return Refueling
	 */
	public function createRefueling(CreateRefuelingCommand $c, User $user): Refueling {
		$refueling = new Refueling ( $c, $user );
		$this->refuelings->add ( $refueling );
		return $refueling;
	}

	/**
	 *
	 * @param CreateMaintenanceCommand $c
	 * @param User $user
	 * @return Maintenance
	 */
	public function createMaintenance(CreateMaintenanceCommand $c, User $user): Maintenance {
		$maintenance = new Maintenance ( $c, $user );
		$this->maintenances->add ( $maintenance );
		return $maintenance;
	}

// 	/**
// 	 * Creates a CUSTOM part for only the current bike.
// 	 *
// 	 * {@inheritdoc}
// 	 * @see \App\Entity\Part\iHasParts::createPart()
// 	 */
// 	public function createPart(CreatePartCommand $c, User $user): Part {
// 		$part = new Part ( $c, $this, $user );
// 		$this->parts->add ( $part );
// 		return $part;
// 	}

	/*
	 * ===============================
	 * Getters
	 * ===============================
	 */

	/**
	 *
	 * @return string|NULL
	 */
	public function getNickname(): ?string {
		return $this->nickname;
	}
	
	/**
	 *
	 * @return string|NULL
	 */
	public function getName(): ?string {
		if(trim($this->getNickname()) == "")
			return $this->getModel()->getName() . " " . $this->getModel()->getAlterName();
			
		return $this->getNickname();
		
	}

	/**
	 *
	 * @return Model
	 */
	public function getModel(): Model {
		return $this->model;
	}

	/**
	 *
	 * @return User
	 */
	public function getOwner(): User {
		return $this->owner;
	}

	/**
	 *
	 * @return float
	 */
	public function getPurchasePrice(): float {
		return $this->purchasePrice;
	}

	/**
	 *
	 * @return int
	 */
	public function getYear(): int {
		return $this->year;
	}

	/**
	 *
	 * @return Collection
	 */
	public function getCustomServiceIntervals(): Collection {
		return $this->customServiceIntervals;
	}

	/**
	 *
	 * @return Collection
	 */
	public function getServiceIntervals(): Collection {
		$intervals = new ArrayCollection ();

		// If we have no overrides
		if ($this->customServiceIntervals->count () == 0)
			return $this->getModel ()->getServiceIntervals ();

		// Find defaults and overriden defaults
		foreach ( $this->getModel ()->getServiceIntervals () as $i ) {
			$intervals->add ( $this->customServiceIntervals->matching ( new Criteria ( new Comparison ( 'task', "=", $i->getTask () ) ) ) [0] ?? $i);

			// $intervals->add ( $this->customServiceIntervals->filter ( function ($custom) use ($i) {
			// return $custom->getTask () == $i->getTask () ? $custom : $i;
			// } ) [0] );
		}

		// Add custom intervals
		foreach ( $this->customServiceIntervals as $i ) {
			if ($intervals->matching ( new Criteria ( new Comparison ( 'task', "=", $i->getTask () ) ) )->isEmpty ())
				$intervals->add ( $i );
		}
		return $intervals;
	}

	/**
	 *
	 * @return string
	 */
	public function getVin(): string {
		return $this->vin;
	}

	/**
	 *
	 * @return Collection
	 */
	public function getMaintenances(): Collection {
		return $this->maintenances;
	}

	/**
	 *
	 * @return Collection
	 */
	public function getRefuelings(): Collection {
		return $this->refuelings;
	}

// 	/**
// 	 * Returns all of this bike's custom parts.
// 	 *
// 	 * @return Collection
// 	 */
// 	public function getCustomParts(): Collection {
// 		return $this->parts;
// 	}

// 	/**
// 	 * Returns all the generic parts from the model AND all the custom ones.
// 	 *
// 	 * {@inheritdoc}
// 	 * @see \App\Entity\Part\iHasParts::getParts()
// 	 */
// 	public function getParts(): Collection {
// 		$parts = new ArrayCollection ();
// 		foreach ( $this->getModel ()->getParts () as $p )
// 			$parts->add ( $p );
// 		foreach ( $this->getCustomParts () as $p )
// 			$parts->add ( $p );
// 		return $parts;
// 	}
}
