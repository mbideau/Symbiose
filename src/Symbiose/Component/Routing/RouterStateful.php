<?php

namespace Falcon\Site\Component\Routing;

use Falcon\Site\Component\Routing,
	Symfony\Component\Routing\Loader\LoaderInterface,
	Falcon\Site\Component\Caching\CacheManagerInterface as CacheManager,
	Falcon\Site\Component\Object\StatefulInterface,
	Symfony\Component\Routing\RouteCollection
;

class RouterStateful
	extends Router
	implements StatefulInterface
{
	protected static $cacheId = 'router';
	protected static $cacheConfigId = 'config';
	
	protected $state;
	
	public function getState()
	{
		return serialize($this->collection);
	}
	
	public function updateState()
	{
		$this->hash = $this->getState();
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
				// get the config from cache
				$config = unserialize($cache->load(self::$cacheConfigId));
				if($config instanceof RouteCollection) {
					// load the routes collection in the router
					if($this->collection != null) {
						$this->collection->addCollection($config);
					}
					else {
						$this->collection = $config;
					}
					// update the hash
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
				// cache the config
				$id = self::$cacheConfigId;
				$content = serialize($this->collection);
				if(!$cache->save($content, $id)) {
					throw new Exception("Can't write to cache '" . self::$cacheId . "'.\n".var_export(array('id' => $id, 'content' => $content), true));
				}
			}
		}
		return $this;
	}
	
	/**
     * Constructor.
     *
     * Available options:
     *
     *   * cache_dir: The cache directory (or null to disable caching)
     *   * debug:     Whether to enable debugging or not (false by default)
     *
     * @param LoaderInterface $loader A LoaderInterface instance
     * @param array           $options  An array of options
     * @param array           $context  The context
     * @param array           $defaults The default values
     *
     * @throws \InvalidArgumentException When unsupported option is provided
     */
    public function __construct(LoaderInterface $loader, CacheManager $cacheManager, array $options = array(), array $context = array(), array $defaults = array())
    {
    	parent::__construct($loader, $cacheManager, $options, $context, $defaults);
    }
}