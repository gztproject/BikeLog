<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\User\User;
use Doctrine\ORM\EntityNotFoundException;
use App\Entity\User\CreateUserCommand;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;

class UsersInitializer implements IEntityInitializer {
	private $manager;
	private $path;
	private $encoder;
	private $loggerInterface;

	/**
	 * Users initializer
	 *
	 * @param string $path
	 *        	Relative path to .tsv file
	 * @param ObjectManager $manager
	 *        	DB manager to use for storing entities
	 * @param UserPasswordEncoderInterface $encoder
	 *        	User password encoder
	 */
	public function __construct(ObjectManager $manager, string $path, UserPasswordEncoderInterface $encoder, LoggerInterface $loggerInterface) {
		$this->manager = $manager;
		$this->path = __DIR__ . $path;
		$this->encoder = $encoder;
		$this->loggerInterface = $loggerInterface;
	}

	/**
	 * Generate users
	 *
	 * @throws EntityNotFoundException Thrown when trying to create a user with nonexisting organization
	 * @return array Array of generated users
	 */
	public function generate(User $migrator): array {
		$fileReader = new ImportFileReader ( $this->loggerInterface );
		$rows = $fileReader->GetRows ( $this->path );

		$users = array ();
		foreach ( $rows as $row ) {

			$cuc = new CreateUserCommand ();

			$cuc->firstName = $row ["FirstName"];
			$cuc->lastName = $row ["LastName"];
			$cuc->username = $row ["UserName"];
			$cuc->email = $row ["EMail"];
			$cuc->mobile = $row ["Mobile"];
			$cuc->isRoleAdmin = $row ["IsRoleAdmin"] === 'TRUE';

			$cuc->password = $row ["Password"];

			$user = new User ( $cuc, $migrator, $this->encoder );

			$this->manager->persist ( $user );
			array_push ( $users, $user );
			$this->manager->flush ();
		}
		return $users;
	}
	public function createDbMigrator(): User {
		$sql = "INSERT INTO `app_users` (`id`, `created_by_id`, `username`, `first_name`, `last_name`, `password`, `roles`, `email`, `mobile`, `is_active`, `created_on`)
				VALUES ('00000000-0000-0000-0000-000000000000','00000000-0000-0000-0000-000000000000','DbMigrator','Database','Migrator','','','','',0, '" . date ( 'Y-m-d H:i:s' ) . "')";

		$stmt = $this->manager->getConnection ()->prepare ( $sql );

		$stmt->execute ();
		$migrator = $this->manager->getRepository ( User::class )->find ( "00000000-0000-0000-0000-000000000000" );
		return $migrator;
	}
}