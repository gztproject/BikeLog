<?php

namespace App\Entity\User;

class CreateUserCommand
{
	public $username;
	public $firstName;
	public $lastName;
	public $plainPassword;
	public $password;
	public $roles;
	public $email;
	public $mobile;	
	public $isRoleAdmin;
	public $oldPassword;
	public $profilePictureFilename;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}	
}
