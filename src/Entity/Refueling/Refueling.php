<?php

namespace App\Entity\Refueling;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Refueling\RefuelingRepository")
 */
class Refueling extends AggregateBase {
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
	* @ORM\ManyToMany(targetEntity="App\Entity\Part\Part", inversedBy="manufacturers")
	*/
	private $parts;
	
	/**
	 * 
	 * @param CreateRefuelingCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateRefuelingCommand $c, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->name = $c->name;
		$this->owner = $c->owner;
	}
	public function update(UpdateRefuelingCommand $c, User $user): Refueling {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}
	public function getName(): ?string {
		return $this->name;
	}
	
}
