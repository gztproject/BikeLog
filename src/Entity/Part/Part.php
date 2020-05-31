<?php

namespace App\Entity\Part;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Task\UpdatePartCommand;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Part\TaskRepository")
 */
class Part extends AggregateBase {

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $name;
	
	/**
	 *
	 * @ORM\ManyToMany(targetEntity="App\Entity\Manufacturer\Manufacturer", inversedBy="parts")
	 */
	private $manufacturers;
		
	/**
	 * 
	 * @param CreatePartCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreatePartCommand $c, Manufacturer $manufacturer, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->name = $c->name;
		$this->manufacturer = $manufacturer;
	}
	
	/**
	 * 
	 * @param UpdatePartCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return Part
	 */
	public function update(UpdatePartCommand $c, User $user): Part {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getName(): string{
		return $this->name;
	}
	
	/**
	 * 
	 * @return Collection
	 */
	public function getManufacturers(): Collection{
		return $this->manufacturers;
	}
}
