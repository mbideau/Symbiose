<?php

namespace Symbiose\Component\Service;

use Symbiose\Component\Logging\FileLogger;

use Symbiose\Component\Service\ServiceContainerAware,
	Symbiose\Component\Object\StatefulInterface,
	Symbiose\Component\Service\Exception\ServiceException as Exception
;

class ServiceStarter
	extends ServiceContainerAware
	implements StatefulInterface
{
	protected $services = array();
	protected $state;
	
	static protected $cacheId = 'starter';
	static protected $cacheConfigId = 'services';
	
	public function __construct($serviceContainer)
	{
		$this->setServiceContainer($serviceContainer);
	}
	
	public function getServiceContainer()
	{
		return $this->serviceContainer;
	}
	
	public function addService($name, $saveCallback, $restoreCallback)
	{
		if(empty($name)) {
			throw new Exception("name can't be empty");
		}
		if(empty($restoreCallback)) {
			throw new Exception("restore callback can't be empty");
		}
		if(!is_string($restoreCallback)) {
			throw new Exception("restore callback must be a string ('" . gettype($restoreCallback) . "' given)");
		}
		if(empty($saveCallback)) {
			throw new Exception("save callback can't be empty");
		}
		if(!is_string($saveCallback)) {
			throw new Exception("save callback must be a string ('" . gettype($saveCallback) . "' given)");
		}
		$this->services[$name] = array(
			'save' => $saveCallback,
			'restore' => $restoreCallback
		);
		return $this;
	}
	
	public function getServices()
	{
		return $this->services;
	}
	
	public function restoreServices(array $order = array())
	{
		// has service to restore
		if(!empty($this->services)) {
			// order was specified
			if(!empty($order)) {
				// restore services in order
				foreach($order as $name) {
					if(array_key_exists($name, $this->services)) {
						if(!$this->serviceContainer->has($name)) {
							throw new Exception("Failed to restore service '$name'. It is not present in the service container.");
						}
						if(!call_user_func(
							array($this->serviceContainer->get($name), $this->services[$name]['restore']),
							array('service_container' => $this->serviceContainer)
						)) {
							FileLogger::directLog("Starter : failed to restore service '$name'");
							return false;
						}
						FileLogger::directLog("Starter : service '$name' restored successfully");
						if($name == 'service_container') {
							$this->serviceContainer = $this->serviceContainer->get('service_container');
						}
					}
				}
				// restore services not in order
				foreach($this->services as $name => $callbacks) {
					if(!$this->serviceContainer->has($name)) {
						throw new Exception("Failed to restore service '$name'. It is not present in the service container.");
					}
					if(!in_array($name, $order)) {
						if(!call_user_func(
							array($this->serviceContainer->get($name), $callbacks['restore']),
							array('service_container' => $this->serviceContainer)
						)) {
							FileLogger::directLog("Starter : failed to restore service '$name'");
							return false;
						}
						FileLogger::directLog("Starter : service '$name' restored successfully");
						if($name == 'service_container') {
							$this->serviceContainer = $this->serviceContainer->get('service_container');
						}
					}
				}
			}
			// no order
			else {
				// restore each service
				foreach($this->services as $name => $callbacks) {
					if(!$this->serviceContainer->has($name)) {
						throw new Exception("Failed to restore service '$name'. It is not present in the service container.");
					}
					if(!call_user_func(
						array($this->serviceContainer->get($name), $callbacks['restore']),
						array('service_container' => $this->serviceContainer)
					)) {
						FileLogger::directLog("Starter : failed to restore service '$name'");
						return false;
					}
					FileLogger::directLog("Starter : service '$name' restored successfully");
					if($name == 'service_container') {
						$this->serviceContainer = $this->serviceContainer->get('service_container');
					}
				}
			}
		}
		return true;
	}
	
	public function saveServices(array $order = array())
	{
		// has service to save
		if(!empty($this->services)) {
			// order was specified
			if(!empty($order)) {
				// save services in order
				foreach($order as $name) {
					if(array_key_exists($name, $this->services)) {
						if(!$this->serviceContainer->has($name)) {
							throw new Exception("Failed to save service '$name'. It is not present in the service container.");
						}
						if(!call_user_func(
							array($this->serviceContainer->get($name), $this->services[$name]['save']),
							array('service_container' => $this->serviceContainer)
						)) {
							FileLogger::directLog("Starter : failed to save service '$name'");
							return false;
						}
						FileLogger::directLog("Starter : service '$name' saved successfully");
					}
				}
				// save services not in order
				foreach($this->services as $name => $callbacks) {
					if(!$this->serviceContainer->has($name)) {
						throw new Exception("Failed to save service '$name'. It is not present in the service container.");
					}
					if(!in_array($name, $order)) {
						 if(!call_user_func(
							array($this->serviceContainer->get($name), $callbacks['save']),
							array('service_container' => $this->serviceContainer)
						)) {
							FileLogger::directLog("Starter : failed to save service '$name'");
							return false;
						}
						FileLogger::directLog("Starter : service '$name' saved successfully");
					}
				}
			}
			// no order
			else {
				// save each service
				foreach($this->services as $name => $callbacks) {
					if(!$this->serviceContainer->has($name)) {
						throw new Exception("Failed to save service '$name'. It is not present in the service container.");
					}
					if(!call_user_func(
						array($this->serviceContainer->get($name), $callbacks['save']),
						array('service_container' => $this->serviceContainer)
					)) {
						FileLogger::directLog("Starter : failed to save service '$name'");
						return false;
					}
					FileLogger::directLog("Starter : service '$name' saved successfully");
				}
			}
		}
		return true;
	}
	
	public function getState()
	{
		return serialize(array(
			$this->services
		));
	}
	
	public function updateState()
	{
		$this->state = $this->getState();
	}
	
	public function setState($state)
	{
		$this->state = $state;
	}
	
	public function restoreState(array $parameters = array())
	{
		FileLogger::directLog('Starter : restoring state ...');
		// if cache manager exists
		if($this->serviceContainer->has('cache_manager')) {
			$cacheManager = $this->serviceContainer->get('cache_manager');
			// it has the cache for starter
			if($cacheManager->hasCache(self::$cacheId)) {
				$cache = $cacheManager->getCache(self::$cacheId);
				// if the config is in cache
				if($cache->test(self::$cacheConfigId)) {
					// get the config from cache
					$config = unserialize($cache->load(self::$cacheConfigId));
					if(is_array($config)) {
						// restore the services
						$this->services = array_merge($this->services, $config);
						// update the state
						$this->updateState();
						FileLogger::directLog('Starter : state restored successfully');
						return true;
					}
				}
			}
		}
		return false;
	}
	
	public function saveState(array $parameters = array())
	{
		// get the current state
		$state = $this->getState();
		// if we need to save it (state is different)
		if($this->state != $state) {
			FileLogger::directLog('Starter : saving state ...');
			// if cache manager exists
			if($this->serviceContainer->has('cache_manager')) {
				$cacheManager = $this->serviceContainer->get('cache_manager');
				// it has the cache for starter
				if($cacheManager->hasCache(self::$cacheId)) {
					$cache = $cacheManager->getCache(self::$cacheId);
					// cache the config
					$id = self::$cacheConfigId;
					$content = serialize($this->services);
					if(!$cache->save($content, $id)) {
						throw new Exception("Can't write to cache '" . self::$cacheId . "'.\n".var_export(array('id' => $id, 'content' => $content), true));
					}
				}
			}
		}
		return $this;
	}
}