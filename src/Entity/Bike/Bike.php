<?php

namespace App\Entity\Bike;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Bike\Bike")
 */
class Bike extends AggregateBase
{
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\User\User", inversedBy="bikes")
	 */
	private $user;
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Model\Model", inversedBy="bikes")
	 */
	private $model;
	
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nickname;
    
    /**
     * @ORM\Column(type="integer")
     */
    private $purchasePrice;    
    

    public function __construct(CreateBikeCommand $c, User $user)
    {
    	if($user == null)
    		throw new \Exception("Can't create entity without a user.");
    	if($c == null)
    		throw new \Exception("Can't create entity without a command.");
    	
    	parent::__construct($user);        
        $this->name = $c->name;
    }
    
    public function update(UpdateBikeCommand $c, User $user): Bike
    {
    	throw new \Exception("Not implemented yet.");
    	parent::updateBase($user);
    	return $this;
    }
   
    public function getNickame(): ?string
    {
        return $this->nickname;
    }
}
