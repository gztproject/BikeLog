<?php

namespace App\Entity\Base;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

abstract class AggregateBaseWithComment extends AggregateBase {
	/**
	 *
	 * @ORM\Column(type="string", length=2048)
	 */
	protected $comment;

	/**
	 * Sets the empty comment, ID and creating user and datetime for the new entity.
	 *
	 * @param User $user
	 *        	User that is creating the entity. (@see methods in User->create...)
	 * @return Uuid Returns the Uuid of created entity.
	 */
	public function __construct(User $user) {
		$this->comment = "";
		return parent::__construct ( $user );
	}

	/**
	 * Returns the comment string.
	 *
	 * @return string
	 */
	public function getComment(): string {
		return $this->comment ?? "";
	}
}
