<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Psr\Log\LoggerInterface;

class AppFixtures extends Fixture {
	private $passwordEncoder;
	private $loggerInterface;

	/**
	 *
	 * @param UserPasswordEncoderInterface $encoder
	 * @param LoggerInterface $loggerInterface
	 */
	public function __construct(UserPasswordEncoderInterface $encoder, LoggerInterface $loggerInterface) {
		$this->passwordEncoder = $encoder;
		$this->loggerInterface = $loggerInterface;
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Common\DataFixtures\FixtureInterface::load()
	 */
	public function load(ObjectManager $manager) {
		$usersInitilizer = new UsersInitializer ( $manager, "/InitData/users.tsv", $this->passwordEncoder, $this->loggerInterface );
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
	}
}


