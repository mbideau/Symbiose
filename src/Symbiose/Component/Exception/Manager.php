<?php

namespace Symbiose\Component\Exception;

use RuntimeException as Exception;

class Manager
{
	protected $defaultExceptionRedirection;
	protected $exceptionRedirections = array();
	
	/*public function handleException(\Exception $e)
	{
		
	}*/
	
	public function setExceptionRedirections(array $exceptionRedirections)
	{
		$this->exceptionRedirections = $exceptionRedirections;
	}
	
	public function setDefaultExceptionRedirection($callableRedirection)
	{
		/*if(empty($callableRedirection)) {
			throw new Exception("ExceptionManager::setDefaultExceptionRedirection : exception redirection is empty");
		}
		if(!is_callable($callableRedirection)) {
			throw new Exception("ExceptionManager::setDefaultExceptionRedirection : exception redirection is not callable");
		}*/
		$this->defaultExceptionRedirection = $callableRedirection;
	}

	public function getDefaultExceptionRedirection()
	{
		return $this->defaultExceptionRedirection;
	}
	
	public function addExceptionRedirection($exceptionClassname, $callableRedirection, $overwrite = true)
	{
		if(empty($exceptionClassname)) {
			throw new Exception("ExceptionManager::addExceptionRedirection : exception classname is empty");
		}
		/*if(empty($callableRedirection)) {
			throw new Exception("ExceptionManager::addExceptionRedirection : exception redirection is empty");
		}
		if(!is_callable($callableRedirection)) {
			throw new Exception("ExceptionManager::addExceptionRedirection : exception redirection is not callable");
		}*/
		if(!$this->hasExceptionRedirection($exceptionClassname) || $overwrite) {
			$this->exceptionRedirections[$exceptionClassname] = $callableRedirection;
		}
		else {
			throw new Exception("ExceptionManager::addExceptionRedirection : exception redirection is already defined for '$exceptionClassname'");
		}
	}
	
	protected function hasExceptionRedirection($exceptionClassname)
	{
		return array_key_exists($exceptionClassname, $this->exceptionRedirections);
	}
	
	public function getExceptionRedirection($exceptionClassname, $onNotFoundReturnDefault = true)
	{
		if(empty($exceptionClassname)) {
			throw new Exception("ExceptionManager::getExceptionRedirection : exception classname is empty");
		}
		if($this->hasExceptionRedirection($exceptionClassname)) {
			return $this->exceptionRedirections[$exceptionClassname];
		}
		else {
			if($onNotFoundReturnDefault) {
				return $this->defaultExceptionRedirection;
			}
		}
		return null;
	}
}