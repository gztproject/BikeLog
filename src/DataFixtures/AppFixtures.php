<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Psr\Log\LoggerInterface;


class AppFixtures extends Fixture
{   	
    private $passwordEncoder;
    private $loggerInterface;
    
    public function __construct(UserPasswordEncoderInterface $encoder, LoggerInterface $loggerInterface)
    {
        $this->passwordEncoder = $encoder;
        $this->loggerInterface = $loggerInterface;
    }
        
    public function load(ObjectManager $manager)
    {   
    	$usersInitilizer = new UsersInitializer($manager, "/InitData/users.tsv", $this->passwordEncoder, $this->loggerInterface);
        $usersInitilizer->generate();
                
    }
}
