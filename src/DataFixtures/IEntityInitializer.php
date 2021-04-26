<?php 

namespace App\DataFixtures;

use App\Entity\User\User;

interface IEntityInitializer
{
	public function generate(User $migrator): array;
}