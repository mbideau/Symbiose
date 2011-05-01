<?php

namespace Symbiose\Component\Caching;

use Symbiose\Component\Caching\CacheManagerInterface,
	Symbiose\Component\Service\ServiceMergeableInterface,
	Symbiose\Component\Caching\Exception\CachingException as Exception,
	Zend\Cache\Manager as BaseCacheManager
;

class CacheManager
	extends BaseCacheManager
	implements CacheManagerInterface, ServiceMergeableInterface
{
	protected $debug;
	
	public function __construct($debug = false)
	{
		// is debug ?
		$this->debug = $debug;
	}
	
	public function loadConfig($file)
	{
		if(!empty($file)) {
			if(!file_exists($file)) {
				throw new Exception("function loadConfig : Configuration file '$file' doesn't exist");
			}
		}
		
		// load config from xml file
		$config = new \Zend\Config\Xml($file);
		$config = $config->toArray();
		
		array_walk_recursive($config, create_function('&$v, $k', 'if($k == "cache_dir"){ $v = realpath($v); }'));
		
		// merge the config to the current
		$this->merge($config);
		
		return $this;
	}
	
	/**
     * Get the configuration template
     * @return array
     */
    public function getCacheTemplates()
    {
        return $this->_optionTemplates;
    }
	
	/**
     * Set the configuration template
     * @return array
     */
    public function setCacheTemplates(array $templates = array())
    {
        $this->_optionTemplates = $templates;
    	return $this;
    }
    
	public function merge($mixed)
	{
		if(!is_array($mixed)) {
			throw new Exception("function merge : parameter must be an array (" . gettype($mixed) . " given)");
		}
		$this->_optionTemplates = array_merge($this->_optionTemplates, $mixed);
		return $this;
	}
}