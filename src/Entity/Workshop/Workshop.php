<?php

namespace App\Entity\Workshop;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Workshop\WorkshopRepository")
 */
class Workshop extends AggregateBase {
	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $name;

	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\User\User", inversedBy="ownedWorkshops")
	 */
	private $owner;

	/**
	 *
	 * @ORM\ManyToMany(targetEntity="App\Entity\User\User", inversedBy="workshops")
	 */
	private $clients;
	
	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\Maintenance\Maintenance", mappedBy="workshop")
	 */
	private $maintenances;

	/**
	 *
	 * @param CreateWorkshopCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateWorkshopCommand $c, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->name = $c->name;
		$this->owner = $user;
		$this->clients = new ArrayCollection ();
	}

	/**
	 *
	 * @param UpdateWorkshopCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return Workshop
	 */
	public function update(UpdateWorkshopCommand $c, User $user): Workshop {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}
	
	/**
	 * 
	 * @param User $client
	 * @param User $user
	 * @return Workshop
	 */
	public function addClient(User $client, User $user): Workshop{
		if ($this->clients->contains($client))
			return $this;
		parent::updateBase($user);
		$this->clients->add($client);
		return $this;
	}

	/*
	 * Getters
	 *
	 */
	public function getName(): string {
		return $this->name;
	}
	public function getOwner(): User {
		return $this->owner;
	}
	public function getClients(): Collection {
		return $this->clients;
	}
	public function hasClient(User $user): bool
	{
		foreach($this->clients as $c)
		{
			if($c == $user)
				return true;
		}
		return false;
	}
	public function getWorkTime(): float
	{
		$total = 0;
		foreach($this->maintenances as $m)
			$total += $m->getSpentTime();
		return $total;
	}
}
