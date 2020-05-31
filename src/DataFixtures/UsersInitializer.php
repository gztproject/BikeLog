<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\User\User;
use App\Entity\Organization\Organization;
use Doctrine\ORM\EntityNotFoundException;
use App\Entity\User\CreateUserCommand;

class UsersInitializer implements IEntityInitializer
{
	private $manager;
    private $organizations;
    private $path;
    private $encoder;
    
    /**
     * Users initializer
     * @param string $path Relative path to .tsv file
     * @param ObjectManager $manager DB manager to use for storing entities
     * @param UserPasswordEncoderInterface $encoder User password encoder
     */    
    public function __construct(ObjectManager $manager, string $path, UserPasswordEncoderInterface $encoder)
    {
    	$this->manager = $manager;
    	$this->path = __DIR__ . $path;
    	$this->encoder = $encoder;
    }
    
    /**     
     * Generate users
     * @throws EntityNotFoundException Thrown when trying to create a user with nonexisting organization 
     * @return array Array of generated users 
     */
    public function generate(): array
    {               
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($this->path);
              
        $users = array();
        foreach ($rows as $row) {
            // create the user and encode its password
            //$user = new User();
            $cuc = new CreateUserCommand();
        	
        	
            $cuc->firstName = $row["FirstName"];
            $cuc->lastName = $row["LastName"];
            $cuc->username = $row["UserName"];
            $cuc->email = $row["EMail"];
            $cuc->mobile = $row["Mobile"];
            $cuc->isRoleAdmin = $row["IsRoleAdmin"]==='TRUE';
            
            $cuc->password = $row["password"];
                      
            $user = new User($cuc, null, $this->encoder);
            
            $this->manager->persist($user);
        	array_push($users, $user);
        	$this->manager->flush();
        }
        return $users;
    }
}