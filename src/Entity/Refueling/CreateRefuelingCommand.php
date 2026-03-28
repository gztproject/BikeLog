<?php

namespace App\Entity\Refueling;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CreateRefuelingCommand
{	
	public $datetime;
	public $odometer;
	public $fuelQuantity;
	public $price;
	public $bike;
	public $isTankFull;
	public $isNotBreakingContinuum;
	public $comment;
	public $latitude;
	public $longitude;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}

	public static function loadValidatorMetadata(ClassMetadata $metadata): void
	{
		$metadata->addPropertyConstraint('datetime', new Assert\NotNull([
			'message' => 'refueling.datetime_required',
		]));
		$metadata->addPropertyConstraint('bike', new Assert\NotNull([
			'message' => 'refueling.bike_required',
		]));
		$metadata->addPropertyConstraint('odometer', new Assert\NotNull([
			'message' => 'refueling.odometer_required',
		]));
		$metadata->addPropertyConstraint('odometer', new Assert\Type([
			'type' => 'numeric',
			'message' => 'refueling.odometer_numeric',
		]));
		$metadata->addPropertyConstraint('odometer', new Assert\GreaterThanOrEqual([
			'value' => 0,
			'message' => 'refueling.odometer_non_negative',
		]));
		$metadata->addPropertyConstraint('fuelQuantity', new Assert\NotNull([
			'message' => 'refueling.fuel_quantity_required',
		]));
		$metadata->addPropertyConstraint('fuelQuantity', new Assert\Positive([
			'message' => 'refueling.fuel_quantity_positive',
		]));
		$metadata->addPropertyConstraint('price', new Assert\NotNull([
			'message' => 'refueling.price_required',
		]));
		$metadata->addPropertyConstraint('price', new Assert\GreaterThanOrEqual([
			'value' => 0,
			'message' => 'refueling.price_non_negative',
		]));
		$metadata->addConstraint(new Assert\Callback('validateOdometer'));
	}

	public function validateOdometer(ExecutionContextInterface $context): void
	{
		if ($this->bike == null || ! is_numeric($this->odometer)) {
			return;
		}

		$minimumOdometer = $this->bike->getPurchaseOdometer();
		$lastRefueling = $this->bike->getLastRefueling();
		$isNewerThanLastRefueling = $lastRefueling != null
			&& $this->datetime instanceof DateTimeInterface
			&& $this->datetime >= $lastRefueling->getDate();

		if ($isNewerThanLastRefueling) {
			$minimumOdometer = max($minimumOdometer, $lastRefueling->getOdometer());
		}

		if ((int) $this->odometer >= $minimumOdometer) {
			return;
		}

		$message = $isNewerThanLastRefueling
			? 'refueling.odometer_below_latest'
			: 'refueling.odometer_below_purchase';

		$context->buildViolation($message)
			->setParameter('%minimum%', (string) $minimumOdometer)
			->atPath('odometer')
			->addViolation();
	}
}
