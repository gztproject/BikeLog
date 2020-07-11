<?php

namespace App\Entity\Task;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\User\User;
use App\Entity\Part\Part;
use App\Entity\Base\AggregateBaseWithComment;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Task\TaskRepository")
 */
class Task extends AggregateBaseWithComment {

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
	 * @param CreateTaskCommand $c
	 * @param User $user
	 * @throws \Exception
	 */
	public function __construct(CreateTaskCommand $c, User $user) {
		if ($user == null)
			throw new \Exception ( "Can't create entity without a user." );
		if ($c == null)
			throw new \Exception ( "Can't create entity without a command." );

		parent::__construct ( $user );
		$this->part = $c->part;
		$this->name = $c->name;
		$this->description = $c->description;
		$this->comment = $c->comment;
	}

	/**
	 *
	 * @param UpdateTaskCommand $c
	 * @param User $user
	 * @throws \Exception
	 * @return Task
	 */
	public function update(UpdateTaskCommand $c, User $user): Task {
		throw new \Exception ( "Not implemented yet." );
		parent::updateBase ( $user );
		return $this;
	}

	/**
	 *
	 * @return Part
	 */
	public function getPart(): Part {
		return $this->part;
	}

	/**
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}
}
