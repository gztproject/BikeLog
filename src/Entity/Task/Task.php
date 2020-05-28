<?php

namespace App\Entity\Task;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Task\TaskRepository")
 */
class Task extends AggregateBase {

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Part\Part")
	 * @ORM\JoinColumn(name="part_id", referencedColumnName="id", nullable=true)
	 */
	private $part;

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $name;
	
	/**
	 *
	 * @ORM\Column(type="string", length=1024)
	 */
	private $description;
	
	/**
	 *
	 * @ORM\Column(type="string", length=2048)
	 */
	private $comment;
		
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
	 * @return Task
	 */
	public function update(UpdateServiceIntervalCommand $c, User $user): ServiceInterval {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}
}
