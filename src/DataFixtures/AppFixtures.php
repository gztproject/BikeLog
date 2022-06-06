<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;;
use Psr\Log\LoggerInterface;

class AppFixtures extends Fixture {
    private $passwordHasher;
	private $loggerInterface;

	/**
	 *
	 * @param UserPasswordEncoderInterface $encoder
	 * @param LoggerInterface $loggerInterface
	 */
	public function __construct(UserPasswordHasherInterface $hasher, LoggerInterface $loggerInterface) {
	    $this->passwordHasher = $hasher;
		$this->loggerInterface = $loggerInterface;
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Common\DataFixtures\FixtureInterface::load()
	 */
	public function load(ObjectManager $manager): void {
	    $usersInitilizer = new UsersInitializer ( $manager, "/InitData/users.tsv", $this->$passwordHasher, $this->loggerInterface );
		$migrator = $usersInitilizer->createDbMigrator ();
		$users = $usersInitilizer->generate ( $migrator );

		$workshopInitializer = new WorkshopsInitializer ( $manager, "/InitData/workshops.tsv", $users, $this->loggerInterface );
		$workshops = $workshopInitializer->generate ( $migrator );

		$mfgInitializer = new ManufacturersInitializer ( $manager, "/InitData/manufacturers.tsv", $this->loggerInterface );
		$mfgs = $mfgInitializer->generate ( $migrator );

		$modelInitializer = new ModelsInitializer ( $manager, "/InitData/models.tsv", "/InitData/model_pictures/", $mfgs, $this->loggerInterface );
		$models = $modelInitializer->generate ( $migrator );

		$bikeInitializer = new BikesInitializer ( $manager, "/InitData/bikes.tsv", $users, $models, $this->loggerInterface );
		$bikes = $bikeInitializer->generate ( $migrator );
		
		$refuelingInitializer = new RefuelingInitializer ( $manager, "/InitData/refuelings.tsv", $users, $this->loggerInterface );
		$refuelings = $refuelingInitializer->generate ( $migrator );
	}
}


