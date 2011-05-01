<?php

namespace Symbiose\Component\AccessControl;

class Role
	implements \Zend_Acl_Role_Interface
{
	protected $role;
	const DEFAULT_ROLE_NAME = 'guest';
	
	public function __construct($role = null)
	{
		if(null == $role) {
			$role = self::DEFAULT_ROLE_NAME;
		}
		$this->role = $role;
	}
	
	public function getRole()
	{
		return $this->role;
	}
	
	public function getRoleId()
	{
		return $this->role;
	}
	
	public function setId($id)
	{
		$this->id = $id;
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function __toString()
	{
		return $this->role;
	}
}