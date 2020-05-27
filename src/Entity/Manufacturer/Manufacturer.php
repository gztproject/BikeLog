<?php

namespace App\Entity\Manufacturer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Manufacturer\Manufacturer")
 */
class Manufacturer extends AggregateBase
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Model\Model", mappedBy="manufacturer")
     */
    private $models;
    
//     /**
//      * @ORM\ManyToMany(targetEntity="App\Entity\Part\Part", mapedBy="manufacturers")
//      */
//     private $parts;

    public function __construct(CreateManufacturerCommand $c, User $user)
    {
    	if($user == null)
    		throw new \Exception("Can't create entity without a user.");
    	if($c == null)
    		throw new \Exception("Can't create entity without a command.");
    	
    	parent::__construct($user);        
        $this->name = $c->name;
        $this->owner = $c->owner;
    }
    
    public function update(UpdateManufacturerCommand $c, User $user): Manufacturer
    {
    	throw new \Exception("Not implemented yet.");
    	parent::updateBase($user);
    	return $this;
    }
   
    public function getName(): ?string
    {
        return $this->name;
    }
    
    public function getParts(): ?Part
    {
        return $this->parts;
    }
}
