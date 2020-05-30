<?php

namespace App\Entity\ServiceInterval;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Model\Model;
use App\Entity\User\User;
use App\Entity\Bike\Bike;
use App\Entity\Task\Task;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\ServiceInterval\ServiceIntervalRepository")
 */
class ServiceInterval extends AggregateBase {

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Task\Task")
	 */
	private $task;

	/**
	 *
	 * @ORM\Column(type="integer")
	 */
	private $interval;

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Model\Model", inversedBy="serviceIntervals")
	 * @ORM\JoinColumn(name="model_id", referencedColumnName="id", nullable=true)
	 */
	private $model;

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Bike\Bike", inversedBy="customServiceIntervals")
	 * @ORM\JoinColumn(name="bike_id", referencedColumnName="id", nullable=true)
	 */
	private $bike;

	/**
	 *
	 * @param CreateServiceIntervalCommand $c
	 * @param iHasServiceIntervals $perishable
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateServiceIntervalCommand $c, iHasServiceIntervals $perishable, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );

		switch (get_class ( $perishable )) {
			case Bike::class :
				$this->bike = $perishable;
				break;
			case Model::class :
				$this->model = $perishable;
				break;
			default :
				throw new \Exception ( 'Not implemented yet.' );
				break;
		}

		$this->interval = $c->interval;
		$this->task = $c->task;
	}

	/**
	 *
	 * @param UpdateServiceIntervalCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return ServiceInterval
	 */
	public function update(UpdateServiceIntervalCommand $c, User $user): ServiceInterval {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}

	/*
	 * ==============================================
	 * Getters
	 * ==============================================
	 */

	/**
	 * Not sure when we actually need this, just leaving it here for now :)
	 * @return iHasServiceIntervals
	 */
	public function getPerishable(): iHasServiceIntervals {
		return $this->bike ?? $this->model;
	}

	/**
	 *
	 * @return int
	 */
	public function getInterval(): int {
		return $this->interval;
	}

	/**
	 *
	 * @return Task
	 */
	public function getTask(): Task {
		return $this->task;
	}
}
