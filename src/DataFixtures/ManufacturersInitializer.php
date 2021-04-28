<?php

namespace App\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use App\Entity\User\User;
use Doctrine\ORM\EntityNotFoundException;
use App\Entity\User\CreateUserCommand;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use App\Entity\Manufacturer\CreateManufacturerCommand;
use App\Entity\Manufacturer\Manufacturer;

class ManufacturersInitializer implements IEntityInitializer {
	private $manager;
	private $path;
	private $loggerInterface;

	/**
	 * Manu8facturers initializer
	 *
	 * @param string $path
	 *        	Relative path to .tsv file
	 * @param ObjectManager $manager
	 *        	DB manager to use for storing entities
	 */
	public function __construct(ObjectManager $manager, string $path, LoggerInterface $loggerInterface) {
		$this->manager = $manager;
		$devpath = __DIR__ . str_replace(".tsv", "-dev.tsv", $path);
		$this->path = file_exists($devpath)?$devpath:__DIR__ . $path;
		$this->loggerInterface = $loggerInterface;
	}

	/**	 
	 * @return array Array of generated manufacturers
	 */
	public function generate(User $migrator): array {
		$fileReader = new ImportFileReader ( $this->loggerInterface );
		$rows = $fileReader->GetRows ( $this->path );

		$mfgs = array ();
		foreach ( $rows as $row ) {

			$cmc = new CreateManufacturerCommand();

			$cmc->name = $row ["name"];			

			$mfg = new Manufacturer( $cmc, $migrator );

			$this->manager->persist ( $mfg );
			array_push ( $mfgs, $mfg );
			$this->manager->flush ();
		}
		return $mfgs;
	}	
}