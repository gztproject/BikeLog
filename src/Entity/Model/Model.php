<?php

namespace App\Entity\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Manufacturer\Manufacturer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Model\Model")
 */
class Model extends AggregateBase
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $alterName;
    
    /**
     * @ORM\Column(type="integer")
     */
    private $displacement;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Manufacturer\Manufacturer", inversedBy="models")
     */
    private $manufacturer;

    public function __construct(CreateModelCommand $c, User $user)
    {
    	if($user == null)
    		throw new \Exception("Can't create entity without a user.");
    	if($c == null)
    		throw new \Exception("Can't create entity without a command.");
    	
    	parent::__construct($user);        
        $this->name = $c->name;
    }
    
    public function update(UpdateModelCommand $c, User $user): Model
    {
    	throw new \Exception("Not implemented yet.");
    	parent::updateBase($user);
    	return $this;
    }
   
    public function getName(): ?string
    {
        return $this->name;
    }
    
    public function getManufacturer(): Manufacturer
    {
        return $this->parts;
    }
}
