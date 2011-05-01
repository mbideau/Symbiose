<?php

namespace Symbiose\Component\ClassLoader;

use Zend\Loader\ClassMapAutoloader,
	Symbiose\Component\Object\StatefulInterface
;

class ClassLoaderStateful
	extends ClassMapAutoloader
	implements StatefulInterface
{
	static protected $cacheId = 'class_loader';
	static protected $cacheConfigId = 'map';
	
	/**
	 * The cache manager instance
	 * @var object
	 */
	protected $cacheManager;
	
	protected $state;
	
	public function autoload($class)
    {
        return parent::autoload($class);
    }
	
	public function getState()
	{
		return serialize($this->map);
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
		elseif(isset($parameters['cache_manager'])) {
			$cacheManager = $parameters['cache_manager'];
		}
		$this->cacheManager = $cacheManager;
		// if the cache manager exists and has the cache for class loader
		if($cacheManager && $cacheManager->hasCache(self::$cacheId)) {
			$cache = $cacheManager->getCache(self::$cacheId);
			if($cache->test(self::$cacheConfigId)) {
				// get the map
				$map = unserialize($cache->load(self::$cacheConfigId));
				if(!empty($map) && is_array($map)) {
					$this->map = $map;
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
				$content = serialize($this->map);
				if(!$cache->save($content, $id)) {
					throw new Exception("Can't write to cache '" . self::$classLoaderCacheId . "'.\n".var_export(array('id' => $id, 'content' => $content), true));
				}
			}
		}
		return $this;
	}
}