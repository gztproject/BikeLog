<?php

namespace App\Entity\User;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Base\AggregateBase;
use App\Entity\Workshop\CreateWorkshopCommand;
use App\Entity\Workshop\Workshop;
use App\Entity\Bike\CreateBikeCommand;
use App\Entity\Bike\Bike;
use App\Entity\Model\CreateModelCommand;
use App\Entity\Model\Model;
use App\Entity\Manufacturer\CreateManufacturerCommand;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Refueling\CreateRefuelingCommand;
use App\Entity\Refueling\Refueling;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\ServiceInterval\ServiceInterval;
use App\Entity\Maintenance\CreateMaintenanceCommand;
use App\Entity\Maintenance\Maintenance;
use App\Entity\MaintenanceTask\CreateMaintenanceTaskCommand;
use App\Entity\MaintenanceTask\MaintenanceTask;
use App\Entity\Task\CreateTaskCommand;
use App\Entity\Task\Task;
use App\Entity\Part\CreatePartCommand;
use App\Entity\Part\Part;

/**
 *
 * @ORM\Table(name="app_users")
 * @ORM\Entity(repositoryClass="App\Repository\User\UserRepository")
 */
class User extends AggregateBase implements UserInterface, \Serializable {
	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $username;

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $firstName;

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $lastName;

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 */
	private $password;

	/**
	 *
	 * @ORM\Column(type="array")
	 */
	private $roles;

	/**
	 *
	 * @ORM\Column(type="string", length=255)
	 * @Assert\NotBlank()
	 * @Assert\Email()
	 */
	private $email;

	/**
	 *
	 * @ORM\Column(type="string", length=20, nullable=true)
	 * @ORM\JoinColumn(nullable=true)
	 */
	private $mobile;

	/**
	 *
	 * @ORM\Column(type="boolean")
	 */
	private $isActive;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\Workshop\Workshop", mappedBy="owner")
	 */
	private $ownedWorkshops;

	/**
	 *
	 * @ORM\OneToMany(targetEntity="App\Entity\Bike\Bike", mappedBy="owner")
	 */
	private $bikes;

	/**
	 *
	 * @ORM\ManyToMany(targetEntity="App\Entity\Workshop\Workshop", mappedBy="clients")
	 */
	private $workshops;

	/**
	 *
	 * @param CreateUserCommand $c
	 * @param User $user
	 * @param UserPasswordEncoderInterface $passwordEncoder
	 * @return \App\Entity\User\User
	 */
	public function __construct(CreateUserCommand $c, User $user, UserPasswordEncoderInterface $passwordEncoder) {
		
		parent::__construct ( $user );

		$this->username = $c->username;
		$this->firstName = $c->firstName;
		$this->lastName = $c->lastName;
		$this->email = $c->email;
		$this->mobile = $c->mobile;

		$this->isActive = true;

		$this->checkPasswordRequirements ( $c->password );
		$this->password = $passwordEncoder->encodePassword ( $this, $c->password );

		$this->roles = array (
				$c->isRoleAdmin ? 'ROLE_ADMIN' : 'ROLE_USER'
		);

		$this->bikes = new ArrayCollection ();
		$this->workshops = new ArrayCollection ();
		$this->ownedWorkshops = new ArrayCollection ();

		return $this;
	}
	public function update(UpdateUserCommand $c, User $user, UserPasswordEncoderInterface $passwordEncoder) {
		parent::updateBase ( $user );
		if ($c->username != null && $c->username != $this->username)
			$this->username = $c->username;
		if ($c->email != null && $c->email != $this->email)
			$this->email = $c->email;
		if ($c->firstName != null && $c->firstName != $this->firstName)
			$this->firstName = $c->firstName;
		if ($c->lastName != null && $c->lastName != $this->lastName)
			$this->lastName = $c->lastName;
		if ($c->mobile != null && $c->mobile != $this->mobile)
			$this->mobile = $c->mobile;

		if (strlen ( $c->password ) != 0) {
			$this->checkPasswordRequirements ( $c->password );
			if ($passwordEncoder->isPasswordValid ( $this, $c->oldPassword ))
				$this->password = $passwordEncoder->encodePassword ( $this, $c->password );
			else
				throw new \Exception ( "Old password is incorrect." );
		}
	}
	private function checkPasswordRequirements(string $password) {
		// ToDo: Read this from application settings?
		$minPasswordLength = 4;
		$passwordMustHaveNumbers = false;
		$passwordMustHaveSpecials = false;

		if (strlen ( $password ) < $minPasswordLength)
			throw new \Exception ( "The password is too short." );

		if ($passwordMustHaveNumbers && preg_match ( '/\\d/', $password ) === 0)
			throw new \Exception ( "The password must contain at least one number." );

		if ($passwordMustHaveSpecials && preg_match ( '/[\'\/~`\!@#\$%\^&\*\(\)_\-\+=\{\}\[\]\|;:"\<\>,\.\?\\\]/', $password ) === 0)
			throw new \Exception ( "The password must contain at least one special character." );
	}

	/**
	 *
	 * @param object $to
	 * @return object
	 */
	public function mapTo(object $to): object {
		if ($to instanceof UpdateUserCommand) {
			$reflect = new \ReflectionClass ( $this );
			$props = $reflect->getProperties ();
			foreach ( $props as $prop ) {
				$name = $prop->getName ();
				if (property_exists ( $to, $name )) {
					$to->$name = $this->$name;
				}
			}
			$to->isRoleAdmin = $this->getIsRoleAdmin ();
			$to->password = "";
		} else {
			throw (new \Exception ( 'Can\'t map ' . get_class ( $this ) . ' to ' . get_class ( $to ) ));
		}
		return $to;
	}

	/*
	 * *****************************************************************
	 * Entity creators (everything should be created by a user)
	 * *****************************************************************
	 */

	/**
	 * Creates a new User.
	 *
	 * @param CreateUserCommand $c
	 * @param UserPasswordEncoderInterface $passwordEncoder
	 * @return User New user
	 */
	public function createUser(CreateUserCommand $c, UserPasswordEncoderInterface $passwordEncoder): User {
		return new User ( $c, $this, $passwordEncoder );
	}

	/**
	 *
	 * @param CreateWorkshopCommand $c
	 * @return Workshop
	 */
	public function createWorkshop(CreateWorkshopCommand $c): Workshop {
		$workshop = new Workshop ( $c, $this );
		$this->ownedWorkshops->add ( $workshop );
		return $workshop;
	}

	/**
	 *
	 * @param CreateBikeCommand $c
	 * @return Bike
	 */
	public function createBike(CreateBikeCommand $c): Bike {
		$bike = new Bike ( $c, $this );
		$this->bikes->add ( $bike );
		return $bike;
	}

	/**
	 *
	 * @param CreateManufacturerCommand $c
	 * @return Manufacturer
	 */
	public function createManufacturer(CreateManufacturerCommand $c): Manufacturer {
		return new Manufacturer ( $c, $this );
	}

	/**
	 *
	 * @param CreatePartCommand $c
	 * @return Part
	 */
	public function createPart(CreatePartCommand $c): Part {
		return new Part ( $c, $this );
	}

	/*
	 * ***************************************************************
	 * Getters (Needed by Symfony)
	 * ***************************************************************
	 */
	public function getUsername(): ?string {
		return $this->username;
	}
	public function getFirstName(): ?string {
		return $this->firstName;
	}
	public function getLastName(): ?string {
		return $this->lastName;
	}
	public function getFullname(): ?string {
		return $this->firstName . " " . $this->lastName;
	}
	public function getPlainPassword(): ?string {
		return null;
	}
	public function getPassword(): ?string {
		return $this->password;
	}
	public function getEmail(): ?string {
		return $this->email;
	}
	public function getMobile(): ?string {
		return $this->mobile;
	}
	public function getIsActive(): ?bool {
		return $this->isActive;
	}
	public function getRoles(): array {
		if ($this->roles == null)
			return array (
					'ROLE_USER'
			);
		return $this->roles;
	}
	public function isEnabled() {
		return $this->isActive;
	}
	public function getIsRoleAdmin(): bool {
		return in_array ( 'ROLE_ADMIN', $this->getRoles () );
	}
	public function __toString(): string {
		return $this->username . ": " . $this->getFullname ();
	}
	public function getBikes(): Collection {
		return $this->bikes;
	}
	public function getWorkshops(): Collection {
		return $this->workshops;
	}
	public function getOwnedWorkshops(): Collection {
		return $this->ownedWorkshops;
	}

	/*
	 * ********************************************************************
	 * Stuff needed by UserInterface and Serializable
	 * ********************************************************************
	 */

	/**
	 *
	 * @see \Serializable::serialize()
	 */
	public function serialize() {
		return serialize ( array (
				$this->id,
				$this->username,
				$this->password,
				$this->isActive
		) );
	}

	/**
	 *
	 * @see \Serializable::unserialize()
	 */
	public function unserialize($serialized) {
		list ( $this->id, $this->username, $this->password, $this->isActive, ) = unserialize ( $serialized );
	}
	public function eraseCredentials() {
		return;
	}
	public function getSalt() {
		return;
	}
}
