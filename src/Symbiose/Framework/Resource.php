<?php

namespace Falcon\Site\Framework;

class Resource
{
	protected $module;
	protected $controller;
	protected $action;
	protected $parameters = array();
	
	public function __construct($module, $controller, $action, array $parameters = null)
	{
		$this->module = $module;
		$this->controller = $controller;
		$this->action = $action;
		$this->parameters = $parameters;
	}
	
	public function __toString()
	{
		return $this->module . '/' . $this->controller . '/' . $this->action;
	}
}