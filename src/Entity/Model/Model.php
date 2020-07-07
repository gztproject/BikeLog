<?php

namespace App\Entity\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Part\CreatePartCommand;
use App\Entity\Part\Part;
use App\Entity\Part\iHasParts;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\ServiceInterval\ServiceInterval;
use App\Entity\User\User;
use App\Entity\ServiceInterval\iHasServiceIntervals;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Model\ModelRepository")
 */
class Model extends AggregateBase implements iHasServiceIntervals {

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $name;

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $alterName;

	/**
	 *
	 * @ORM\Column(type="integer")
	 */
	private $displacement;

	/**
	 *
	 * @ORM\Column(type="integer")
	 */
	private $yearFrom;

	/**
	 *
	 * @ORM\Column(type="integer")
	 */
	private $yearTo;

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Manufacturer\Manufacturer", inversedBy="models")
	 */
	private $manufacturer;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\ServiceInterval\ServiceInterval", mappedBy="model")
	 */
	private $serviceIntervals;

	/**
	 *
	 * @ORM\Column(type="array")
	 */
	private $vinRanges;
	
	
	/**
	 *
	 * @ORM\Column(type="string")
	 */
	private $pictureFilename;

	// /**
	// *
	// * @ORM\ManyToMany(targetEntity="App\Entity\Part\Part", inversedBy="models")
	// */
	// private $parts;

	/**
	 *
	 * @param CreateModelCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateModelCommand $c, Manufacturer $manufacturer, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );

		$this->manufacturer = $manufacturer;
		$this->name = $c->name;
		$this->alterName = $c->alterName;
		$this->displacement = $c->displacement;
		$this->yearFrom = $c->yearFrom;
		$this->yearTo = $c->yearTo;

		$this->vinRanges = $c->vinRanges;

		$this->serviceIntervals = new ArrayCollection ();

		$this->pictureFilename = $c->pictureFilename ?? "";
	}

	/**
	 *
	 * @param UpdateModelCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return Model
	 */
	public function update(UpdateModelCommand $c, User $user): Model {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}

	/**
	 * Creates a GENERIC(!) model service interval.
	 *
	 * @param CreateServiceIntervalCommand $c
	 * @param User $user
	 * @return ServiceInterval
	 */
	public function createServiceInterval(CreateServiceIntervalCommand $c, User $user): ServiceInterval {
		$si = new ServiceInterval ( $c, $this, $user );
		$this->serviceIntervals->add ( $si );
		return $si;
	}

	// /**
	// * Creates a generic part on a model.
	// * {@inheritDoc}
	// * @see \App\Entity\Part\iHasParts::createPart()
	// */
	// public function createPart(CreatePartCommand $c, User $user): Part {
	// $part = new Part($c, $this, $user);
	// $this->parts->add($part);
	// return $part;
	// }

	/*
	 * =================================================
	 * Getters
	 * =================================================
	 */

	/**
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 *
	 * @return string
	 */
	public function getAlterName(): string {
		return $this->alterName;
	}

	/**
	 *
	 * @return Manufacturer
	 */
	public function getManufacturer(): Manufacturer {
		return $this->parts;
	}

	/**
	 *
	 * @return array
	 */
	public function getVinRanges(): array {
		return $this->vinRanges;
	}

	/**
	 *
	 * @return int
	 */
	public function getYearFrom(): int {
		return $this->yearFrom;
	}

	/**
	 *
	 * @return int
	 */
	public function getYearTo(): int {
		return $this->yearTo;
	}

	/**
	 *
	 * @return int
	 */
	public function getDisplacement(): int {
		return $this->displacement;
	}

	/**
	 * Returns all the generic serviceIntervals from the model.
	 *
	 * {@inheritdoc}
	 * @see \App\Entity\ServiceInterval\iHasServiceIntervals::getServiceIntervals()
	 */
	public function getServiceIntervals(): Collection {
		return $this->serviceIntervals;
	}

	/**
	 *
	 * @return string
	 */
	public function getPictureFilename(): string {
		if (trim ( $this->pictureFilename ) == "")
			return "img/No_motorcycle.png";
		return "uploads/models/" . $this->pictureFilename;
	}

	// /**
	// * Returns all the generic parts from the model.
	// * {@inheritDoc}
	// * @see \App\Entity\Part\iHasParts::getParts()
	// */
	// public function getParts(): Collection {
	// return $this->parts;
	// }
}
