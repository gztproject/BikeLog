<?php

namespace App\Entity\Refueling;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBaseWithComment;
use App\Entity\Bike\Bike;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Refueling\RefuelingRepository")
 */
class Refueling extends AggregateBaseWithComment {
	/**
	 *
	 * @ORM\Column(type="datetime")
	 */
	private $datetime;

	/**
	 *
	 * @ORM\Column(type="integer")
	 */
	private $odometer;

	/**
	 *
	 * @ORM\Column(type="decimal", precision=5, scale=2)
	 */
	private $fuelQuantity;

	/**
	 *
	 * @ORM\Column(type="decimal", precision=5, scale=2)
	 */
	private $price;

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Bike\Bike", inversedBy="refuelings")
	 */
	private $bike;
	
	/**
	 *
	 * @ORM\OneToOne(targetEntity="App\Entity\Refueling\Refueling", nullable=true)
	 */
	private $previousRefueling;

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

	/**
	 *
	 * @param UpdateRefuelingCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return Refueling
	 */
	public function update(UpdateRefuelingCommand $c, User $user): Refueling {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}

	/**
	 *
	 * @return Bike
	 */
	public function getBike(): Bike {
		return $this->bike;
	}

	/**
	 * @deprecated Use getDate() instead.
	 * @return \DateTimeInterface
	 */
	public function getDatetime(): \DateTimeInterface {
		return $this->datetime;
	}
	
	
	/**
	 *
	 * @return \DateTimeInterface
	 */
	public function getDate(): \DateTimeInterface {
		return $this->datetime;
	}

	/**
	 *
	 * @return string
	 */
	public function getDateString(): string {
		return $this->datetime->format ( 'j. n. Y' );
	}

	/**
	 *
	 * @return string
	 */
	public function getTimeString(): string {
		return $this->datetime->format ( 'H:i:s' );
	}

	/**
	 *
	 * @return string
	 */
	public function getDateTimeString(): string {
		return $this->datetime->format ( 'j. n. Y, H:i:s' );
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
	 * @return int
	 */
	public function getDistance(): int {
		return 0;
	}

	/**
	 *
	 * @return float
	 */
	public function getFuelQuantity(): float {
		return $this->fuelQuantity;
	}

	/**
	 *
	 * @return float
	 */
	public function getPrice(): float {
		return $this->price;
	}
}
