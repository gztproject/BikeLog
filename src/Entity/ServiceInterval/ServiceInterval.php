<?php

namespace App\Entity\ServiceInterval;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\User\User;

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
	 * @ORM\ManyToOne(targetEntity="App\Entity\Model\Model", inversedBy="customServiceIntervals")
	 * @ORM\JoinColumn(name="bike_id", referencedColumnName="id", nullable=true)
	 */
	private $bike;
		
	/**
	 * 
	 * @param CreateServiceIntervalCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateServiceIntervalCommand $c, User $user) {
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
}
