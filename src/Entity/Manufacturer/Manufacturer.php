<?php

namespace App\Entity\Manufacturer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Part\CreatePartCommand;
use App\Entity\Part\Part;
use App\Entity\User\User;
use App\Entity\Model\Model;
use App\Entity\Model\CreateModelCommand;
use App\Entity\Part\iHasParts;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Manufacturer\ManufacturerRepository")
 */
class Manufacturer extends AggregateBase implements iHasParts {
	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $name;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\Model\Model", mappedBy="manufacturer")
	 */
	private $models;

	/**
	 *
	 * @ORM\ManyToMany(targetEntity="App\Entity\Part\Part", inversedBy="manufacturers")
	 */
	private $parts;

	/**
	 *
	 * @param CreateManufacturerCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateManufacturerCommand $c, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->name = $c->name;
		$this->models = new ArrayCollection ();
		$this->parts = new ArrayCollection ();
	}

	/**
	 *
	 * @param UpdateManufacturerCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return Manufacturer
	 */
	public function update(UpdateManufacturerCommand $c, User $user): Manufacturer {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}

	/**
	 *
	 * @param CreatePartCommand $c
	 * @param User $user
	 * @return Part
	 */
	public function createPart(CreatePartCommand $c, User $user): Part {
		parent::updateBase ( $user );
		$part = new Part ( $c, $this, $user );
		$this->parts->add ( $part );
		return $part;
	}

	/**
	 *
	 * @param Part $part
	 * @param User $user
	 * @return Part
	 */
	public function addPart(Part $part, User $user): Part {
		if($this->parts->contains($part))
			return $part;
		parent::updateBase ( $user );
		$this->parts->add ( $part );
		return $part;
	}

	/**
	 *
	 * @param CreateModelCommand $c
	 * @param User $user
	 * @return Model
	 */
	public function createModel(CreateModelCommand $c, User $user): Model {
		$model = new Model ( $c, $this, $user );
		$this->models->add ( $model );
		return $model;
	}

	/**
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 *
	 * @return Collection
	 */
	public function getModels(): Collection {
		return $this->models;
	}

	/**
	 *
	 * @return Collection
	 */
	public function getParts(): Collection {
		return $this->parts;
	}
}
