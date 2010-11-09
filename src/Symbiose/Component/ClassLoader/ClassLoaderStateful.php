<?php

namespace Falcon\Site\Component\ClassLoader;

use Falcon\Site\Component\ClassLoader\ClassLoader,
	Falcon\Site\Component\Object\StatefulInterface
;

class ClassLoaderStateful
	extends ClassLoader
	implements StatefulInterface
{
	static protected $cacheId = 'class_loader';
	static protected $cacheConfigId = 'config';
	
	protected $state;
	
	public function getState()
	{
		return serialize(array(
			'env' => $this->envName,
			'debug' => $this->debug,
			'namespaces' => $this->namespaces,
			'prefixes' => $this->prefixes,
			'tempCache' => $this->tempCache
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
		$cacheManager = $this->cacheManager;
		// use the service container in the parameters to get the cache manager
		if(array_key_exists('service_container', $parameters)) {
			$sc = $parameters['service_container'];
			if($sc->has('cache_manager')) {
				$cacheManager = $sc->get('cache_manager');
			}
		}
		// if the cache manager exists and has the cache for class loader
		if($cacheManager && $cacheManager->hasCache(self::$cacheId)) {
			$cache = $cacheManager->getCache(self::$cacheId);
			if($cache->test(self::$cacheConfigId)) {
				// get the config
				$config = unserialize($cache->load(self::$cacheConfigId));
				if(!empty($config) && is_array($config)) {
					// restore the class loader config
					$this->registerNamespaces($config['namespaces']);
					$this->registerPrefixes($config['prefixes']);
					$this->tempCache = array_merge($config['tempCache'], $this->tempCache);
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
			$cacheManager = $this->cacheManager;
			// use the service container in the parameters to get the cache manager
			if(array_key_exists('service_container', $parameters)) {
				$sc = $parameters['service_container'];
				if($sc->has('cache_manager')) {
					$cacheManager = $sc->get('cache_manager');
				}
			}
			// if the cache manager exists and has the cache for class loader
			if($cacheManager && $cacheManager->hasCache(self::$cacheId)) {
				$cache = $cacheManager->getCache(self::$cacheId);
				// save the config
				$id = self::$cacheConfigId;
				$content = serialize(array(
					'namespaces' => $this->namespaces,
					'prefixes' => $this->prefixes,
					'tempCache' => $this->tempCache
				));
				if(!$cache->save($content, $id)) {
					throw new Exception("Can't write to cache '" . self::$classLoaderCacheId . "'.\n".var_export(array('id' => $id, 'content' => $content), true));
				}
			}
		}
		return $this;
	}
}