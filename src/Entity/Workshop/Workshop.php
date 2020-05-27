<?php

namespace App\Entity\Workshop;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Workshop\Workshop")
 */
class Workshop extends AggregateBase
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User\User", inversedBy="ownedWorkshops")
     */
    private $owner;
    
    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\User\User", inversedBy="workshops")
     */
    private $clients;

    public function __construct(CreateWorkshopCommand $c, User $user)
    {
    	if($user == null)
    		throw new \Exception("Can't create entity without a user.");
    	if($c == null)
    		throw new \Exception("Can't create entity without a command.");
    	
    	parent::__construct($user);        
        $this->name = $c->name;
        $this->owner = $c->owner;
    }
    
    public function update(UpdateWorkshopCommand $c, User $user): Country
    {
    	throw new \Exception("Not implemented yet.");
    	parent::updateBase($user);
    	return $this;
    }
   
    public function getName(): ?string
    {
        return $this->name;
    }
    
    public function getOwner(): User
    {
        return $this->owner;
    }
}
