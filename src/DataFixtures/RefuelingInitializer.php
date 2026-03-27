<?php

namespace App\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use App\Entity\User\User;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use App\Entity\Bike\Bike;
use App\Entity\Refueling\CreateRefuelingCommand;
use DateTime;

class RefuelingInitializer implements IEntityInitializer {
	private $manager;
	private $path;
	private $loggerInterface;
	private $users;

	/**
	 * Refuelings initializer
	 *
	 * @param string $path
	 *        	Relative path to .tsv file
	 * @param ObjectManager $manager
	 *        	DB manager to use for storing entities
	 */
	public function __construct(ObjectManager $manager, string $path, array $users, LoggerInterface $loggerInterface) {
		$this->manager = $manager;
		$devpath = __DIR__ . str_replace ( ".tsv", "-dev.tsv", $path );
		$this->path = file_exists ( $devpath ) ? $devpath : __DIR__ . $path;
		$this->loggerInterface = $loggerInterface;
		$this->users = $users;
	}

	/**
	 *
	 * @return array Array of generated refuelings
	 */
	public function generate(User $migrator): array {
		$fileReader = new ImportFileReader ( $this->loggerInterface );
		$rows = $fileReader->GetRows ( $this->path );

		$refuelings = array ();
		foreach ( $rows as $row ) {
			$this->loggerInterface->info ( "*********************************** Processing row ********************************" );

			$owner = $this->findUser ( $row ["owner"] );
			$crc = new CreateRefuelingCommand ();
			$bike = $this->findBike ( $row ["bike"], $owner );

			if ($bike == null)
			{
				$this->loggerInterface->Error("Bike not found!");
				continue;
			}

			$crc->datetime = new DateTime ( $row ["date"] ?? "2000-01-01");
			$crc->odometer = $row ["odometer"] ?? 0;
			$crc->fuelQuantity = $row ["fuelQuantity"] ?? 0;
			$crc->price = $row ["price"] ?? 0;
			$crc->isTankFull = true;
			$crc->isNotBreakingContinuum = true;

			$refueling = $bike->createRefueling ( $crc, $owner );

			$this->manager->persist ( $refueling );
			array_push ( $refuelings, $refueling );
			$this->manager->flush ();
		}
		return $refuelings;
	}

	/**
	 *
	 * @param String $username
	 * @return User|NULL
	 */
	private function findUser(String $username): ?User {
		foreach ( $this->users as $u ) {
			if ($u->getUsername () === trim ( $username )) {
				return $u;
			}
		}
		return null;
	}

	/**
	 *
	 * @param String $bike,
	 *        	User $user
	 * @return Bike|NULL
	 */
	private function findBike(String $bike, User $user): ?Bike {
		foreach ( $user->getBikes () as $b ) {
			if ($b->getName () === trim ( $bike )) {
				return $b;
			}
		}
		return null;
	}
}