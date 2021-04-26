<?php

namespace App\Entity\Part;

use Doctrine\Common\Collections\Collection;
use App\Entity\User\User;

interface iHasParts {
	public function getParts(): Collection;
	public function createPart(CreatePartCommand $c, User $user): Part;
}