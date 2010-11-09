<?php

namespace Falcon\Site\Component\AccessControl;

class Resource
	implements \Zend_Acl_Resource_Interface
{
	protected $id;
	protected $resource;
	
	public function __construct($resource)
	{
		$this->resource = $resource;
	}
	
	public function getResourceId()
	{
		return $this->resource;
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
		return $this->resource;
	}
}