<?php

namespace Falcon\Site\Framework\Module;

use Falcon\Site\Framework\Module\ModuleManager,
	Falcon\Site\Component\Object\StatefulInterface
;

class ModuleManagerStateful
	extends ModuleManager
	implements StatefulInterface
{
	static protected $cacheId = 'module_manager';
	static protected $cacheConfigId = 'config';
	
	protected $state;
	
	public function getState()
	{
		return serialize(array(
			$this->modulesDirs,
			$this->configurationFilename,
			$this->modules,
			$this->bootstrapList
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
		$sc = $this->serviceContainer;
		// use the service container in the parameters to get the cache manager
		if(array_key_exists('service_container', $parameters)) {
			$sc = $parameters['service_container'];
		}
		$cacheManager = $sc->has('cache_manager') ? $sc->get('cache_manager') : null;
		// if the cache manager exists and has the cache for cache manager
		if($cacheManager && $cacheManager->hasCache(self::$cacheId)) {
			$cache = $cacheManager->getCache(self::$cacheId);
			if($cache->test(self::$cacheConfigId)) {
				//$mmFile = $cache->getBackend()->getFile(self::$moduleManagerInstanceCacheId);
				$config = unserialize($cache->load(self::$cacheConfigId));
				if(!empty($config) && is_array($config)) {
					$this->modulesDirs = $config['modulesDirs'];
					$this->configurationFilename = $config['configurationFilename'];
					$this->modules = $config['modules'];
					$this->bootstrapList = $config['bootstrapList']; 
					$this
						// update the reference to the service container
						->setServiceContainer($sc)
						// update the reference to the service container of modules
						->updateModulesServiceContainer()
					;
					// update the state
					$this->updateState();
					return true;
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
			$cacheManager = $this->serviceContainer->has('cache_manager') ? $this->serviceContainer->get('cache_manager') : null;
			// use the service container in the parameters to get the cache manager
			if(array_key_exists('service_container', $parameters)) {
				$sc = $parameters['service_container'];
				if($sc->has('cache_manager')) {
					$cacheManager = $sc->get('cache_manager');
				}
			}
			// if the cache manager exists and has the cache for cache manager
			if($cacheManager && $cacheManager->hasCache(self::$cacheId)) {
				$cache = $cacheManager->getCache(self::$cacheId);
				$id = self::$cacheConfigId;
				$content = serialize(array(
					'modulesDirs' => $this->modulesDirs,
					'configurationFilename' => $this->configurationFilename,
					'modules' => $this->modules,
					'bootstrapList' => $this->bootstrapList
				));
				// save it to the cache
				if(!$cache->save($content, $id)) {
					throw new Exception("Can't write to cache '" . self::$cacheId . "'.\n".var_export(array('id' => $id, 'content' => $content), true));
				}
			}
		}
		return $this;
	}
}