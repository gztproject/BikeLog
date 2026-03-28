<?php

namespace App\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User\User;
use Doctrine\ORM\EntityNotFoundException;
use App\Entity\User\CreateUserCommand;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;

class UsersInitializer implements IEntityInitializer {
    private ObjectManager $manager;
	private string $path;
	private UserPasswordHasherInterface $hasher;
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
	public function __construct(ObjectManager $manager, string $path, UserPasswordHasherInterface $hasher, LoggerInterface $loggerInterface) {
		$this->manager = $manager;
		$devpath = __DIR__ . str_replace(".tsv", "-dev.tsv", $path);
		$this->path = file_exists($devpath)?$devpath:__DIR__ . $path;
		$this->hasher = $hasher;
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

			$user = new User ( $cuc, $migrator, $this->hasher );

			$this->manager->persist ( $user );
			array_push ( $users, $user );
			$this->manager->flush ();
		}
		return $users;
	}
	
	public function createDbMigrator(): User {
		$sql = "INSERT INTO `app_users` (`id`, `created_by_id`, `username`, `first_name`, `last_name`, `password`, `roles`, `email`, `mobile`, `is_active`, `created_on`, `profile_picture_filename`)
				VALUES ('00000000-0000-0000-0000-000000000000','00000000-0000-0000-0000-000000000000','DbMigrator','Database','Migrator','','','','',0, '" . date ( 'Y-m-d H:i:s' ) . "', '')";

		$stmt = $this->manager->getConnection ()->prepare ( $sql );

		$stmt->execute ();
		$migrator = $this->manager->getRepository ( User::class )->find ( "00000000-0000-0000-0000-000000000000" );
		return $migrator;
	}
}