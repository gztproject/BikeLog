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
	 * @ORM\OneToOne(targetEntity="App\Entity\Refueling\Refueling")
	 */
	private $previousRefueling;

	/**
	 *
	 * @ORM\Column(type="boolean")
	 */
	private $isTankFull;

	/**
	 *
	 * @param CreateRefuelingCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateRefuelingCommand $c, Bike $bike, ?Refueling $previousRefueling, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->bike = $bike;
		$this->comment = $c->comment ?? "";
		$this->datetime = $c->datetime;
		$this->fuelQuantity = $c->fuelQuantity;
		$this->isTankFull = $c->isTankFull;
		$this->odometer = $c->odometer;
		$this->price = $c->price;
		$this->previousRefueling = $c->isNotBreakingContinuum ? $previousRefueling : null;
	}

	/**
	 *
	 * @param Refueling $r
	 * @param User $user
	 * @throws Exception
	 * @return Refueling
	 */
	public function setPreviousRefueling(?Refueling $r, User $user): Refueling {
		parent::updateBase ( $user );
		if ($r != null) {
			if ($r->getDate () > $this->getDate ())
				throw new Exception ( "Previous refueling date (" . $r->getDateTimeString () . ") is bigger
											than the current one (" . $this->getDateTimeString () . ")." );
			if ($r->getOdometer () > $this->getOdometer ())
				throw new Exception ( "Previous odometer state (" . $r->getOdometer () . ") is bigger
											than the current odometer state (" . $this->getOdometer () . ")." );
		}
		$this->previousRefueling = $r;
		return $this;
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
	 *
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
	 * @return int|NULL
	 */
	public function getDistance(): ?int {
		if ($this->previousRefueling != null)
			return $this->getOdometer () - $this->previousRefueling->getOdometer ();
		return null;
	}

	/**
	 *
	 * @return Refueling|NULL
	 */
	public function getPreviousRefueling(): ?Refueling {
		return $this->previousRefueling;
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

	/**
	 *
	 * @return float|NULL
	 */
	public function getConsumption(): ?float {
		if (! $this->isValid ())
			return null;
		return $this->getFuelQuantity () / ($this->getDistance () / 100.00);
	}

	/**
	 *
	 * @return bool
	 */
	public function isValid(): bool {
		return $this->getPreviousRefueling () != null;
	}

	/**
	 *
	 * @return string
	 */
	public function __toString(): string {
		return "Refueling: " . $this->getDateString () . ", " . $this->getOdometer () . " km, " . $this->getFuelQuantity () . " l.";
	}
}
