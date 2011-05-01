<?php

namespace Symbiose\Component\Object;

use Symbiose\Component\Object\Exception\ObjectException as Exception;

abstract class PublicAccess
{
	public function __call($name, $arguments) {
        // get or set method
        if(strpos($name, 'get') === 0 || strpos($name, 'set') === 0) {
        	// class name
        	$className = get_class($this);
        	// get property name
        	$propertyName = lcfirst(preg_replace('#^get|^set#', '', $name));
        	// if the property doesn't exists
        	if(!property_exists($className, $propertyName)) {
        		throw new Exception("function __call : property '$propertyName' is undefined for class '$className'");
        	}
        	// get method
	        if(strpos($name, 'get') === 0) {
	        	return $this->$propertyName;
	        }
	        // set method
	        elseif(strpos($name, 'set') === 0) {
	        	$this->$propertyName = reset($arguments);
	        	return $this;
	        }
        }
        // unknown method
        else {
        	throw new Exception("function __call : method '$name' is undefined");
        }
    }
}