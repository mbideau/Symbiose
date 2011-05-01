<?php

namespace Symbiose\Component\Optimization;

use Symbiose\Component\Service\ServiceContainerAware,
	RuntimeException as Exception
;

class Combinator
	extends ServiceContainerAware
{
	static protected $cacheId = 'combinator';
	
	protected $cacheManagerService;
	
	public function combine(array $files, $key = null)
	{
		if(empty($files)) {
			throw new Exception("You must provide an array of files contents (not empty)");
		}
		
		// get cache key
		$cacheKey = !empty($key) && is_string($key) ? $key : $this->getCacheKey($files);
		
		// not in cache
		if(!$this->isCached($cacheKey)) {
			$combined = '';
			foreach($files as $uid => $content) {
				/*echo "<strong>$uid</strong><pre>" . print_r($content, true) . '</pre>';*/
				$combined .= "\n/* ===== $uid */\n" . $content . "\n";
			}
			// save it in cache
			$this->cache($cacheKey, $combined);
		}
		return $this->getCache($cacheKey);
	}
	
	protected function getCacheKey(array $files)
	{
		$sorted = asort(array_keys($files));
		return md5(implode("\n", $sorted));
	}
	
	public function isCached($key)
	{
		$isCached = false;
		// if we can use the cache
		if($this->getCacheManagerService()) {
			// get the cache
			$cache = $this->getCacheManagerService()->getCache(self::$cacheId);
			// if cache exists
			if(!empty($cache)) {
				// if the cache contains the value
				$isCached = $cache->test($key);
			}
		}
		return $isCached;
	}
	
	public function getCache($key, $returnFilePath = false)
	{
		// if we can use the cache
		if($this->getCacheManagerService()) {
			// get the cache
			$cache = $this->getCacheManagerService()->getCache(self::$cacheId);
			// if cache exists
			if(!empty($cache)) {
				// if the cache contains the value
				if($cache->test($key)) {
					if($returnFilePath) {
						return $cache->getBackend()->getFile(self::$cacheId . '_' . $key);
					}
					return $cache->load($key);
				}
			}
		}
		return null;
	}
	
	protected function cache($key, $content)
	{
		// if we can use the cache
		if($this->getCacheManagerService()) {
			// get the cache
			$cache = $this->getCacheManagerService()->getCache(self::$cacheId);
			// if cache exists
			if(!empty($cache)) {
				// save the content to the cache
				if(!$cache->save($content, $key)) {
					throw new Exception("Can't write to cache '" . self::$cacheId . "'.\n".var_export(array('id' => $key, 'content' => $content), true));
				}
			}
		}
	}
}