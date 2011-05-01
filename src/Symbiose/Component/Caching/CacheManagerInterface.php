<?php

namespace Symbiose\Component\Caching;

use Zend\Cache\Frontend;

interface CacheManagerInterface
{
	public function loadConfig($file);
	public function getCacheTemplates();
	public function setCacheTemplates(array $templates = array());
	
	// from Zend_Cache_Manager
	public function setCache($name, Frontend $cache);
	public function hasCache($name);
	public function getCache($name);
	public function setCacheTemplate($name, $options);
	public function hasCacheTemplate($name);
	public function getCacheTemplate($name);
	public function setTemplateOptions($name, $options);
}