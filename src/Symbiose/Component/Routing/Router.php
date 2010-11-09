<?php

namespace Falcon\Site\Component\Routing;

use Symfony\Component\Routing\Router as BaseRouter,
	Symfony\Component\Routing\Loader\LoaderInterface,
	Falcon\Site\Component\Caching\CacheManagerInterface as CacheManager,
	Falcon\Site\Component\Routing\Exception\RoutingException as Exception,
	Symfony\Component\Routing\RouteCollection,
	Symfony\Component\Routing\Resource\FileResource
;

class Router
	extends BaseRouter
{
	protected static $cacheId = 'router';
	
	protected $cacheManager;
	
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
    	$this->cacheManager = $cacheManager;
    	parent::__construct($loader, null, $options, $context, $defaults);
    }
	
    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function getRouteCollection()
    {
    	if($this->collection == null && !empty($this->resource)) {
    		$this->collection = $this->loader->load($this->resource);
    	}
    	return $this->collection;
    }
    
	public function merge($file)
	{
		$collection = $this->loader->load($file);
		if(!empty($collection)) {
			if(!empty($this->collection)) {
				$this->collection->addCollection($collection);
			}
			else {
				$this->collection = $collection;
			}
		}
	}
	
	/**
     * Gets the UrlMatcher instance associated with this Router.
     *
     * @return UrlMatcherInterface A UrlMatcherInterface instance
     */
    public function getMatcher()
    {
        if (null !== $this->matcher) {
            return $this->matcher;
        }

        if (null === $this->cacheManager || null === $this->options['matcher_cache_class']) {
            return $this->matcher = new $this->options['matcher_class']($this->getRouteCollection(), $this->context, $this->defaults);
        }

        $class = $this->options['matcher_cache_class'];
        if ($this->needsReload($class)) {
            $dumper = new $this->options['matcher_dumper_class']($this->getRouteCollection());

            $options = array(
                'class'      => $class,
                'base_class' => $this->options['matcher_base_class'],
            );

            $this->updateCache($class, $dumper->dump($options));
        }

        $classContent = $this->getFromCache($class);
        //@todo FIXME use require instead of eval
        eval($classContent);

        return $this->matcher = new $class($this->context, $this->defaults);
    }
	
	/**
     * Gets the UrlGenerator instance associated with this Router.
     *
     * @return UrlGeneratorInterface A UrlGeneratorInterface instance
     */
    public function getGenerator()
    {
        if (null !== $this->generator) {
            return $this->generator;
        }

        if (null === $this->cacheManager || null === $this->options['generator_cache_class']) {
            return $this->generator = new $this->options['generator_class']($this->getRouteCollection(), $this->context, $this->defaults);
        }

        $class = $this->options['generator_cache_class'];
        if($this->needsReload($class)) {
            $dumper = new $this->options['generator_dumper_class']($this->getRouteCollection());

            $options = array(
                'class'      => $class,
                'base_class' => $this->options['generator_base_class'],
            );

            $this->updateCache($class, $dumper->dump($options));
        }

        $classContent = $this->getFromCache($class);
        //@todo FIXME use require instead of eval
        eval($classContent);

        return $this->generator = new $class($this->context, $this->defaults);
    }
    
    protected function getSafeId($id)
	{
		return strtolower(preg_replace('#[^a-zA-Z0-9_]+#', '', $id));
	}
    
    protected function getFromCache($class)
    {
    	// if we have a cache manager
		if($this->cacheManager) {
			// try to get the cache
			if($this->cacheManager->hasCache(self::$cacheId)) {
				$cache = $this->cacheManager->getCache(self::$cacheId);
				$safeId = $this->getSafeId($class);
				if($cache->test($safeId)) {
					// get the content
					$content = unserialize($cache->load($safeId));
					return $content;
				}
			}
		}
		return null;				
    }
    
	protected function updateCache($class, $dump)
    {
        $this->saveToCache($class, $dump);

        if($this->options['debug']) {
        	$this->saveToCache("${class}_meta", $this->getRouteCollection()->getResources());
        }
    }

    protected function needsReload($class)
    {
        $content = $this->getFromCache($class);
    	if(empty($content)) {
            return true;
        }

        if (!$this->options['debug']) {
            return false;
        }

        $metadata = $this->getFromCache("${class}_meta");
        if(empty($metadata)) {
            return true;
        }
        
        $lastModificationTime = $this->getCacheMTime($class);
        if(!empty($lastModificationTime)) {
        	foreach ($metadata as $resource) {
        		// if resource if just a filename, convert it to a FileResource
        		if(is_string($resource)) {
        			$resource = new FileResource($resource);
        		}
	        	if(!$resource->isUptodate($lastModificationTime)) {
	                return true;
	            }
	        }
        }

        return false;
    }

    protected function getCacheMTime($id)
    {
    	// if we have a cache manager
		if($this->cacheManager) {
			// only if cache manager has the cache for the router
			if($this->cacheManager->hasCache(self::$cacheId)) {
				$cache = $this->cacheManager->getCache(self::$cacheId);
				$safeId = $this->getSafeId($id);
				$mtime = $cache->test($safeId);
				if(is_int($mtime)) {
					return $mtime;
				}
			}
		}
		return null;
    }
    
    protected function saveToCache($id, $content)
    {
        // if we have a cache manager
		if($this->cacheManager) {
			// only if cache manager has the cache for the router
			if($this->cacheManager->hasCache(self::$cacheId)) {
				$cache = $this->cacheManager->getCache(self::$cacheId);
				$safeId = $this->getSafeId($id);
				$content = serialize(preg_replace('#^<\?php#', '', $content));
				// save content to the cache
				if(!$cache->save($content, $safeId)) {
					throw new Exception("Failed to write in cache.\n".var_export(array('id' => $safeId, 'content' => $content), true));
				}
			}
		}
		return $this;
    }
}