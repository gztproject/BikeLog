<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class AppFixtures extends Fixture
{   	
    private $passwordEncoder;
    
    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->passwordEncoder = $encoder;
    }
        
    public function load(ObjectManager $manager)
    {   
        $usersInitilizer = new UsersInitializer($manager, "/InitData/users.tsv", $this->passwordEncoder);
        $usersInitilizer->generate();
                
    }
}
