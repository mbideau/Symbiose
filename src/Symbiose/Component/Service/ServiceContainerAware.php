<?php

namespace Falcon\Site\Component\Service;

use Falcon\Site\Component\Service\ServiceContainerAwareInterface,
	Falcon\Site\Component\Service\Exception\ServiceException as Exception;
;

abstract class ServiceContainerAware
	implements ServiceContainerAwareInterface
{
	protected $serviceContainer;
	
	public function setServiceContainer(ServiceContainer $sc)
	{
		$this->serviceContainer = $sc;
		return $this;
	}
	
	public function __call($name, $arguments) {
		// get service method
        if(preg_match('#^get(([A-Z][a-z]*)+)Service$#', $name, $matches)) {
        	// class name
        	$className = get_class($this);
        	
        	// service name
        	$serviceName = $matches[1];
        	
        	// get property name
        	$propertyName = lcfirst($serviceName) . 'Service';
        	/*
        	var_dump(array(
        		'$name' => $name,
        		'$className' => $className,
        		'$matches' => $matches,
        		'$serviceName' => $serviceName,
        		'$propertyName' => $propertyName
        	));
        	*/
        	// if the property doesn't exists
        	if(!property_exists($className, $propertyName)) {
        		throw new Exception("function __call : property '$propertyName' is undefined for class '$className'");
        	}
        	
        	// if the service is null
        	if(!$this->$propertyName) {
        		$this->$propertyName = $this->serviceContainer->$name();
        	}
        	
        	return $this->$propertyName;
        }
        // unknown method
        else {
        	throw new Exception("function __call : method '$name' is undefined");
        }
    }
}