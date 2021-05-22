<?php

namespace App\Service;

use Symfony\Component\Security\Core\Security;
use Doctrine\Common\Collections\Collection;

class BikeList 
{
	private $security;
	public function __construct(Security $security) 
	{
		// Avoid calling getUser() in the constructor: auth may not
		// be complete yet. Instead, store the entire Security object.
		$this->security = $security;
	}
	
	public function getList(): Collection 
	{
		$user = $this->security->getUser ();
		$myBikes = $user->getBikes ();
		return $myBikes;
	}
}