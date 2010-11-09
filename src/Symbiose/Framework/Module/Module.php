<?php

namespace Falcon\Site\Framework\Module;

use Falcon\Site\Framework\Module\Exception\ModuleException as Exception,
	Falcon\Site\Component\Object\PublicAccess,
	Symfony\Component\HttpFoundation\File\File as File,
	Falcon\Site\Component\Service\Loader\XmlFileLoader as ServiceContainerLoader,
	Falcon\Site\Component\Service\ServiceContainer,
	Falcon\Site\Component\Service\ServiceContainerAware
;

class Module
	extends ServiceContainerAware
{
	protected $routerService;
	protected $classLoaderService;
	protected $cacheManagerService;
	
	protected $path;
	protected $name;
	protected $version;
	protected $dependencies;
	protected $order;
	protected $registerClasses = array();
	protected $servicesFile;
	protected $controllersDir;
	protected $cacheFile;
	protected $routeFile;
	
	protected $bootstraped = false;
	
	public function __call($name, $arguments) {
		// get or set method (not service)
        if(!preg_match('#^get\w+Service$#', $name) && strpos($name, 'get') === 0 || strpos($name, 'set') === 0) {
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
        	return parent::__call($name, $arguments);
        }
    }
	
	public function getControllerPath($controllerFilename)
	{
		// if controllers dir is empty
		if(empty($this->controllersDir)) {
			throw new Exception(__FUNCTION__ . " : controllers dir is empty");
		}
		// return the contoller path
		return $this->controllersDir . DS . $controllerFilename;
	}
	
	public function __sleep()
	{
		return array(
			'path',
			'name',
			'version',
			'dependencies',
			'order',
			'registerClasses',
			'servicesFile',
			'controllersDir',
			'cacheFile',
			'routeFile'
		);
	}
	
	public function __construct($name, $path)
	{
		$this->name = $name;
		$this->path = $path;
	}
	
	protected function getServiceContainerFromFile($filepath)
	{
		$sc = null;
		// if the file doesn't exist
		if(!file_exists($filepath)) {
			throw new Exception(__FUNCTION__ . " : service file '$filepath' doesn't exist");
		}
		// get a file object
		$scFileObject = new File($filepath);
		// get extension of file
		$scFileExtension = $scFileObject->getDefaultExtension();
		// if the extension is empty
		if(empty($scFileExtension)) {
			throw new Exception(__FUNCTION__ . " : failed to get extension of service file '$filepath'");
		}
		// according to extension
		switch($scFileExtension) {
			case '.xml':
				// new service container
				$sc = new ServiceContainer();
				// service container loader
				$scl = new ServiceContainerLoader($sc);
				// load the service container from the file
				$scl->load($filepath);
				break;
			// extension is not xml
			default:
				throw new Exception(__FUNCTION__ . " : extension '$serviceFileExtension' of service file '$filepath' is not supported");
				break;
		}
		return $sc;
	}
	
	public function bootstrap()
	{
		// if the module is not bootstraped
		if(!$this->bootstraped) {
			// register classes
			if(!empty($this->registerClasses)) {
				// register them
				$this->getClassLoaderService()->registerNamespaces($this->registerClasses['namespaces']);
				$this->getClassLoaderService()->registerPrefixes($this->registerClasses['prefixes']);
			}
			
			// controllers dir
			if(!empty($this->controllersDir)) {
				// if controllers dir doesn't exist
				if(!is_dir($this->controllersDir)) {
					throw new Exception(__FUNCTION__ . " : controllers dir '" . $this->controllersDir . "' doesn't exist");
				}
			}
			
			// services file
			if(!empty($this->servicesFile)) {
				// get the services file path
				$path = !file_exists($this->servicesFile) && file_exists(dirname($this->path) . DS . $this->servicesFile) ? dirname($this->path) . DS . $this->servicesFile : $this->servicesFile;
				// if the services file exists
				if(file_exists($path)) {
					// get the new service container generated from the file
					$sc = $this->getServiceContainerFromFile($path);
					/*
					// freeze the service container (if not already frozen)
					if(!empty($sc) && !$sc->isFrozen()) {
						$sc->freeze();
					}
					*/
					// merge the services file
					$this->serviceContainer->merge($sc);
				}
			}
			
			// caching file
			if(!empty($this->cacheFile)) {
				// get the caching file path
				$path = !file_exists($this->cacheFile) && file_exists(dirname($this->path) . DS . $this->cacheFile) ? dirname($this->path) . DS . $this->cacheFile : $this->cacheFile;
				// if the routes file exists
				if(file_exists($path)) {
					// get the caching rules from file
					$cachingRules = new \Zend_Config_Xml($path);
					$cachingRules = $cachingRules->toArray();
					// if there are caching rules
					if(!empty($cachingRules)) {
						// if the cache manager service exists
						if($this->getCacheManagerService()) {
							// merge the caching rules
							$this->getCacheManagerService()->merge($cachingRules);
						}
					}
				}
			}
			
			// routes file
			if(!empty($this->routeFile)) {
				// get the routes file path
				$path = !file_exists($this->routeFile) && file_exists(dirname($this->path) . DS . $this->routeFile) ? dirname($this->path) . DS . $this->routeFile : $this->routeFile;
				// if the routes file exists
				if(file_exists($path)) {
					// if the router service exists
					if($this->getRouterService()) {
						// merge the routes file
						$this->getRouterService()->merge($path);
					}
				}
			}
			
			// the module is bootstraped
			$this->bootstraped = true;
		}
	}
}