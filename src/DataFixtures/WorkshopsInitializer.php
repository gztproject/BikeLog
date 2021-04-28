<?php

namespace App\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use App\Entity\User\User;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use App\Entity\Workshop\CreateWorkshopCommand;
use App\Entity\Workshop\Workshop;

class WorkshopsInitializer implements IEntityInitializer {
	private $manager;
	private $path;
	private $loggerInterface;
	private $users;

	/**
	 * Workshops initializer
	 *
	 * @param string $path
	 *        	Relative path to .tsv file
	 * @param ObjectManager $manager
	 *        	DB manager to use for storing entities
	 */
	public function __construct(ObjectManager $manager, string $path, array $users, LoggerInterface $loggerInterface) {
		$this->manager = $manager;
		$devpath = __DIR__ . str_replace(".tsv", "-dev.tsv", $path);
		$this->path = file_exists($devpath)?$devpath:__DIR__ . $path;
		$this->loggerInterface = $loggerInterface;
		$this->users = $users;
	}

	/**	 
	 * @return array Array of generated workshops
	 */
	public function generate(User $migrator): array {
		$fileReader = new ImportFileReader ( $this->loggerInterface );
		$rows = $fileReader->GetRows ( $this->path );

		$workshops = array ();
		foreach ( $rows as $row ) {

			$cwc = new CreateWorkshopCommand();

			$cwc->name = $row ["name"];			
			$workshop = new Workshop( $cwc, $this->findUser($row ["owner"]));
			
			foreach(explode(",", $row["clients"]) as $client){
				$workshop->addClient($this->findUser($client), $this->findUser($row ["owner"]));				
			}

			$this->manager->persist ( $workshop );
			array_push ( $workshops, $workshop );
			$this->manager->flush ();
		}
		return $workshops;
	}	
	
	/**
	 * 
	 * @param String $username
	 * @return User|NULL
	 */
	private function findUser(String $username): ?User{
		foreach ( $this->users as $u ) {
			if ($u->getUsername () === trim($username)) {
				return $u;
			}
		}
		return null;
	}
}