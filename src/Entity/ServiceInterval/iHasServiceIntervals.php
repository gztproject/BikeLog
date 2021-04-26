<?php

namespace App\Entity\ServiceInterval;

use Doctrine\Common\Collections\Collection;
use App\Entity\User\User;

interface iHasServiceIntervals {
	/**
	 * 
	 * @return Collection
	 */
	public function getServiceIntervals(): Collection;
	
	/**
	 * Creates a generic or custom interval override.
	 * @param CreateServiceIntervalCommand $c
	 * @param User $user
	 * @return ServiceInterval
	 */
	public function createServiceInterval(CreateServiceIntervalCommand $c, User $user): ServiceInterval;
}