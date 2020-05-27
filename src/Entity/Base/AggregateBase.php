<?php

namespace App\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use App\Entity\User\User;
use DateTime;

abstract class AggregateBase extends Base
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="uuid")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $id;    
    
    /**
     * Sets the creating user and datetime for the new entity. 
     * @param User $user User that is creating the entity. (@see methods in User->create...)
     * @return Uuid Returns the Uuid of created entity.
     */
    public function __construct(User $user)
    {
    	$this->id = Uuid::uuid1();
    	parent::__construct($user);
    	return $this->id;
    }    
       
    /**
     * Returns the entity UUID
     * @return Uuid
     */
    public function getId(): Uuid
    {
        return $this->id;
    }    
    
}
