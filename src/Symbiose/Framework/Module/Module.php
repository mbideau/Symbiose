<?php

namespace Symbiose\Framework\Module;

use Symbiose\Framework\Module\Exception\ModuleException as Exception,
	Symbiose\Component\Object\PublicAccess,
	Symfony\Component\HttpFoundation\File\File as File,
	Symbiose\Component\Service\Loader\XmlFileLoader as ServiceContainerLoader,
	Symbiose\Component\Service\ServiceContainer,
	Symbiose\Component\Service\ServiceContainerAware,
	Symbiose\Component\ClassLoader\ClassLoaderStateful as ClassLoader
;

class Module
	extends ServiceContainerAware
{
	protected $routerService;
	protected $classLoaderService;
	protected $cacheManagerService;
	protected $loggerService;
	
	protected $path;
	protected $name;
	protected $version;
	protected $dependencies = array();
	protected $order = array();
	protected $registerClasses = array();
	protected $servicesFile;
	protected $controllersDir;
	protected $cacheFile;
	protected $routeFile;
	protected $classmapFile;
	protected $preloaders = array();
	
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
		return $this->controllersDir . '/' . $controllerFilename;
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
			'routeFile',
			'classmapFile',
			'preloaders',
			'bootstraped'
		);
	}
	
	public function __construct($name, $path)
	{
		$this->name = $name;
		$this->path = $path;
	}
	
	public function bootstrap()
	{
		! $this->getLoggerService() ?: $this->getLoggerService()->debug('Module-' . $this->getName() . ': bootstraping module ...');
		// if the module is not bootstraped
		if(!$this->bootstraped) {
			$this
				->preloadClasses($this->preloaders)
				->registerClassmap($this->classmapFile)
				->registerClasses($this->registerClasses)
				->checkControllerDir($this->controllersDir)
				->mergeServices($this->servicesFile)
				->mergeCachingRules($this->cacheFile)
				->mergeRoutesDefinitions($this->routeFile)
			;
			// the module is bootstraped
			$this->bootstraped = true;
		}
		// already bootstraped
		else {
			! $this->getLoggerService() ?: $this->getLoggerService()->warn('Module: already bootstraped');
			/*$this
				->preloadClasses($this->preloaders)
				->registerClassmap($this->classmapFile)
			;*/
		}
	}
	
	protected function preloadClasses(array $files)
	{
		if(!empty($files)) {
			// load prefixed first
			if(isset($files['prefixed']) && !empty($files['prefixed'])) {
				foreach($files['prefixed'] as $f) {
					include $f;
				}
			}
			if(isset($files['namespaced']) && !empty($files['namespaced'])) {
				foreach($files['namespaced'] as $f) {
					include $f;
				}
			}
		}
		return $this;
	}
	
	protected function registerClassmap($file)
	{
		if(!empty($file)) {
			$this->serviceContainer->getClassLoaderService()->registerAutoloadMap($file);
		}
		return $this;
	}
	
	protected function registerClasses($classes)
	{
		// register classes
		if(!empty($classes)) {
			// with new standard autoloader
			$cl = new ClassLoader('development', true);
			$cl->registerNamespaces($classes['namespaces']);
			$cl->registerPrefixes($classes['prefixes']);
			$cl->register();
		}
		! $this->getLoggerService() ?: $this->getLoggerService()->debug('Module-' . $this->getName() . ': registered classes added to class loader');
		return $this;
	}
	
	protected function checkControllerDir($dir)
	{
		// controllers dir
		if(!empty($dir)) {
			// if controllers dir doesn't exist
			if(!is_dir($dir)) {
				throw new Exception(__FUNCTION__ . " : controllers dir '$dir' doesn't exist");
			}
			! $this->getLoggerService() ?: $this->getLoggerService()->debug('Module-' . $this->getName() . ': controller dir added');
		}
		return $this;
	}
	
	protected function mergeServices($file)
	{
		// services file
		if(!empty($file)) {
			// get the services file path
			$path = !file_exists($file) && file_exists(dirname($this->path) . '/' . $file) ? dirname($this->path) . '/' . $file : $file;
			// if the services file exists
			if(file_exists($path)) {
				// get the new service container generated from the file
				$sc = ServiceContainer::getServiceContainerFromFile($path);
				// merge the services file
				$this->serviceContainer->merge($sc);
				! $this->getLoggerService() ?: $this->getLoggerService()->debug('Module-' . $this->getName() . ': services merged');
			}
		}
		return $this;
	}
	
	protected function mergeCachingRules($file)
	{
		// caching file
		if(!empty($file)) {
			// get the caching file path
			$path = !file_exists($file) && file_exists(dirname($this->path) . '/' . $file) ? dirname($this->path) . '/' . $file : $file;
			// if the routes file exists
			if(file_exists($path)) {
				// get the caching rules from file
				$cachingRules = new \Zend\Config\Xml($path);
				$cachingRules = $cachingRules->toArray();
				// if there are caching rules
				if(!empty($cachingRules)) {
					// if the cache manager service exists
					if($this->getCacheManagerService()) {
						// merge the caching rules
						$this->getCacheManagerService()->merge($cachingRules);
						! $this->getLoggerService() ?: $this->getLoggerService()->debug('Module-' . $this->getName() . ': caching rules merged');
					}
				}
			}
		}
		return $this;
	}
	
	protected function mergeRoutesDefinitions($file)
	{
		// routes file
		if(!empty($file)) {
			// get the routes file path
			$path = !file_exists($file) && file_exists(dirname($this->path) . '/' . $file) ? dirname($this->path) . '/' . $file : $file;
			// if the routes file exists
			if(file_exists($path)) {
				// if the router service exists
				if($this->getRouterService()) {
					// merge the routes file
					$this->getRouterService()->merge($path);
					! $this->getLoggerService() ?: $this->getLoggerService()->debug('Module-' . $this->getName() . ': routes definitions merged');
				}
			}
		}
		return $this;
	}
}