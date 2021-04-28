<?php

namespace App\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use App\Entity\User\User;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use App\Entity\Model\Model;
use App\Entity\Bike\CreateBikeCommand;

class BikesInitializer implements IEntityInitializer {
	private $manager;
	private $path;
	private $loggerInterface;
	private $users;
	private $models;

	/**
	 * Bikes initializer
	 *
	 * @param string $path
	 *        	Relative path to .tsv file
	 * @param ObjectManager $manager
	 *        	DB manager to use for storing entities
	 */
	public function __construct(ObjectManager $manager, string $path, array $users, array $models, LoggerInterface $loggerInterface) {
		$this->manager = $manager;
		$devpath = __DIR__ . str_replace ( ".tsv", "-dev.tsv", $path );
		$this->path = file_exists ( $devpath ) ? $devpath : __DIR__ . $path;
		$this->loggerInterface = $loggerInterface;
		$this->users = $users;
		$this->models = $models;
	}

	/**
	 *
	 * @return array Array of generated bikes
	 */
	public function generate(User $migrator): array {
		$fileReader = new ImportFileReader ( $this->loggerInterface );
		$rows = $fileReader->GetRows ( $this->path );

		$bikes = array ();
		foreach ( $rows as $row ) {

			$owner = $this->findUser ( $row ["owner"] );

			$cbc = new CreateBikeCommand ();

			$cbc->nickname = $row ["nickname"] ?? "";
			$model = $this->findModel ( $row ["model"] );
			if($model == null)
				continue;
			$cbc->model = $model;
			$cbc->purchaseOdometer = $row ["purchaseOdometer"] ?? 0;
			$cbc->purchasePrice = $row ["purchasePrice"] == "" ? 0 : $row ["purchasePrice"];
			$cbc->vin = $row ["vin"] ?? "";
			$cbc->year = $row ["year"] ?? 2000;
			$cbc->pictureFilename = $row ["picture"];
			$cbc->fuelTanksize = $row ["fuelTankSize"];

			$bike = $owner->createBike ( $cbc );

			$this->manager->persist ( $bike );
			array_push ( $bikes, $bike );
			$this->manager->flush ();
		}
		return $bikes;
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
	 * @param String $model
	 * @return Model|NULL
	 */
	private function findModel(String $model): ?Model {
		foreach ( $this->models as $m ) {
			if ($m->getName () === trim ( $model ) || $m->getAlterName () === trim ( $model )) {
				return $m;
			}
		}
		return null;
	}
}