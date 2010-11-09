<?php

namespace Falcon\Site\Component\Caching;

use Falcon\Site\Component\Caching\CacheManager,
	Falcon\Site\Component\Object\StatefulInterface
;

class CacheManagerStateful
	extends CacheManager
	implements StatefulInterface
{
	static protected $cacheId = 'cache_manager';
	static protected $cacheConfigId = 'config';
	
	protected $state;
	
	public function getState()
	{
		return serialize(array(
			$this->_optionTemplates
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
		$cacheManager = $this;
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
			if($cache->test(self::$cacheConfigId)) {
				// get the config from cache
				$config = unserialize($cache->load(self::$cacheConfigId));
				if(is_array($config)) {
					// load the templates in the cache manager
					$this->_optionTemplates = array_merge($this->_optionTemplates, $config);
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
			$cacheManager = $this;
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
				// cache the config
				$id = self::$cacheConfigId;
				$content = serialize($this->_optionTemplates);
				if(!$cache->save($content, $id)) {
					throw new Exception("Can't write to cache '" . self::$cacheId . "'.\n".var_export(array('id' => $id, 'content' => $content), true));
				}
			}
		}
		return $this;
	}
}