<?php

namespace App\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use App\Entity\User\User;
use Doctrine\ORM\EntityNotFoundException;
use App\Entity\User\CreateUserCommand;
use Psr\Log\LoggerInterface;
use Exception;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use App\Entity\Model\CreateModelCommand;
use App\Entity\Model\Model;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\ServiceInterval\CreateServiceIntervalCommand;
use App\Entity\Task\CreateTaskCommand;
use App\Entity\ServiceInterval\IntervalTypes;
use App\Entity\Part\CreatePartCommand;

class ModelsInitializer implements IEntityInitializer {
	private $manager;
	private $path;
	private $picturePath;
	private $logger;
	private $mfgs;

	/**
	 * Models initializer
	 *
	 * @param string $path
	 *        	Relative path to .tsv file
	 * @param ObjectManager $manager
	 *        	DB manager to use for storing entities
	 */
	public function __construct(ObjectManager $manager, string $path, string $picture_path, array $mfgs, LoggerInterface $loggerInterface) {
		$this->manager = $manager;
		$devpath = __DIR__ . str_replace(".tsv", "-dev.tsv", $path);
		$this->path = file_exists($devpath)?$devpath:__DIR__ . $path;
		$this->picturePath = __DIR__ . $picture_path;
		$this->logger = $loggerInterface;
		$this->mfgs = $mfgs;
	}

	/**
	 *
	 * @return array Array of generated bike models
	 */
	public function generate(User $migrator): array {
		$fileReader = new ImportFileReader ( $this->logger );
		$rows = $fileReader->GetRows ( $this->path );

		$models = array ();
		$parts = array ();
		foreach ( $rows as $row ) {

			$mfg = $this->getManufacturer ( $row ["manufacturer"] );
			if ($mfg == null)
				throw new EntityNotFoundException ( 'Manufacturer ' . $row ["manufacturer"] . ' doesn\'t exist.' );

			$cmc = new CreateModelCommand ();

			$cmc->name = $row ["name"];
			$cmc->alterName = $row ["alterName"];
			$cmc->displacement = $row ["displacement"];
			$cmc->vinRanges = explode ( ",", $row ["vinRanges"] );
			$cmc->yearFrom = $row ["yearFrom"];
			$cmc->yearTo = $row ["yearTo"];
			$this->logger->debug ( $row ["fuelTankSize"] );
			$cmc->fuelTankSize = $row ["fuelTankSize"];

			if ($row ["imageFile"]) {
				$path = $this->picturePath . $row ["imageFile"];
				$originalFilename = pathinfo ( $path, PATHINFO_FILENAME );
				// this is needed to safely include the file name as part of the URL
				$safeFilename = transliterator_transliterate ( 'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename );
				$newFilename = $safeFilename . '-' . uniqid () . '.' . pathinfo ( $path, PATHINFO_EXTENSION );

				// Move the file to the directory where brochures are stored

				$newPath = realpath ( __DIR__ . "/../../public/uploads/models" ) . "/" . $newFilename;
				$this->logger->debug ( "Moving " . $path . " to " . $newPath );
				copy ( $path, $newPath );

				$cmc->pictureFilename = $newFilename;
			}

			$model = $mfg->createModel ( $cmc, $migrator );
			echo("Created model ". $model . "\n");
			$this->logger->info ( "Checking for service intervals:" );
			$intervals = array_filter ( $row, function ($item) {
				if (strpos ( $item, "interval_" ) !== FALSE)
					return TRUE;
				return FALSE;
			}, ARRAY_FILTER_USE_KEY );

			foreach ( $intervals as $task => $interval ) {
				if (trim ( $interval ) == "" || $interval == 0)
					continue;
				$this->logger->info ( "Creating a new service interval: " . $task . " -> " . $interval );
				// $task is in format interval_[task]_[part]
				$this->logger->debug ( "Naming info: " . implode ( "//", explode ( "_", $task ) ) );
				$cpc = new CreatePartCommand ();
				$cpc->name = ucfirst ( strtolower ( implode ( " ", preg_split ( '/(?=[A-Z])/', explode ( "_", $task ) [2] ) ) ) );

				$part = null;
				foreach ( $parts as $p ) {
					if ($p->getName () == $cpc->name) {
						$part = $p;
						$mfg->addPart ( $part, $migrator );
						break;
					}
				}
				if ($part == null) {
					$this->logger->debug ( "Creating part: " . $cpc->name );
					$part = $mfg->createPart ( $cpc, $migrator );
					$this->manager->persist ( $part );
					array_push($parts, $part);
				}

				
				$ctc = new CreateTaskCommand ();
				$ctc->part = $part;
				$ctc->name = ucfirst ( explode ( "_", $task ) [1] . " " . strtolower ( implode ( " ", preg_split ( '/(?=[A-Z])/', explode ( "_", $task ) [2] ) ) ) );
				$ctc->description = $ctc->name;
				$this->logger->debug ( "Creating task: " . $ctc->name );
				$task = $model->createTask ( $ctc, $migrator );
				$this->manager->persist ( $task );

				$cic = new CreateServiceIntervalCommand ();
				$cic->interval = $interval;
				$cic->intervalType = IntervalTypes::relative;
				$cic->task = $task;
				$this->logger->debug ( "Creating service interval: " . $task->getName () . " every " . $interval . " km." );
				$serviceInterval = $model->createServiceInterval ( $cic, $migrator );
				$this->manager->persist ( $serviceInterval );
			}

			$this->manager->persist ( $model );
			$this->manager->persist ( $mfg );
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