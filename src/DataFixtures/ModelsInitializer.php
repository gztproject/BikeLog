<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\User\User;
use Doctrine\ORM\EntityNotFoundException;
use App\Entity\User\CreateUserCommand;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use App\Entity\Model\CreateModelCommand;
use App\Entity\Model\Model;
use App\Entity\Manufacturer\Manufacturer;

class ModelsInitializer implements IEntityInitializer {
	private $manager;
	private $path;
	private $loggerInterface;
	private $mfgs;

	/**
	 * Models initializer
	 *
	 * @param string $path
	 *        	Relative path to .tsv file
	 * @param ObjectManager $manager
	 *        	DB manager to use for storing entities
	 */
	public function __construct(ObjectManager $manager, string $path, array $mfgs, LoggerInterface $loggerInterface) {
		$this->manager = $manager;
		$this->path = __DIR__ . $path;
		$this->loggerInterface = $loggerInterface;
		$this->mfgs = $mfgs;
	}

	/**
	 *
	 * @return array Array of generated bike models
	 */
	public function generate(User $migrator): array {
		$fileReader = new ImportFileReader ( $this->loggerInterface );
		$rows = $fileReader->GetRows ( $this->path );

		$models = array ();
		foreach ( $rows as $row ) {

			$mfg = $this->getManufacturer ( $row ["manufacturer"] );
			if ($mfg == null)
				throw new EntityNotFoundException ( 'Manufacturer ' . $row ["manufacturer"] . ' doesn\'t exist.' );

			$cmc = new CreateModelCommand ();

			$cmc->name = $row ["name"];
			$cmc->alterName = $row ["alterName"];
			$cmc->displacement = $row ["displacement"];
			$cmc->vinRanges = explode(",", $row ["vinRanges"]);
			$cmc->yearFrom = $row ["yearFrom"];
			$cmc->yearTo = $row ["yearTo"];
			$cmc->fuelTankSize = $row ["fuelTankSize"];

			$model = $mfg->createModel($cmc, $migrator);

			$this->manager->persist ( $model );
			array_push ( $models, $model );
			$this->manager->flush ();
		}
		return $models;
	}

	/**
	 *
	 * @param string $name
	 * @return Manufacturer
	 */
	private function getManufacturer(string $name): Manufacturer {
		foreach ( $this->mfgs as $mfg ) {
			if ($mfg->getName () === $name) {
				return $mfg;
			}
		}
		return null;
	}
}