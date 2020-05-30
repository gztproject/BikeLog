<?php

namespace App\Entity\Bike;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Bike\BikeRepository")
 */
class Bike extends AggregateBase {

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
	 * @ORM\Column(type="integer")
	 */
	private $purchasePrice;
	
	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\ServiceInterval\ServiceInterval", mappedBy="bike")
	 */
	private $customServiceIntervals;
	
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
	 * 
	 * @return string|NULL
	 */
	public function getNickame(): ?string {
		return $this->nickname;
	}
}