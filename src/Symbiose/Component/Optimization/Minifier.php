<?php

namespace Symbiose\Component\Optimization;

use Symbiose\Component\Service\ServiceContainerAware,
	Symfony\Component\HttpFoundation\File\File,
	Symbiose\Component\Optimization\Minifier\Css as MinifierCss,
	Symbiose\Component\Optimization\Minifier\Js as MinifierJs,
	RuntimeException as Exception
;

class Minifier
	extends ServiceContainerAware
{
	static protected $cacheId = 'minifier';
	
	protected $cacheManagerService;
	
	public function minify($file, $key = null)
	{
		if(empty($file)) {
			throw new Exception('You must provide a file');
		}
		
		// get cache key
		$cacheKey = !empty($key) && is_string($key) ? $key : $this->getCacheKey($file);
		
		// not in cache
		if(!$this->isCached($cacheKey)) {
			// get file extension
			try {
				$fileObject = new File($file);
				$extension = preg_replace('#^\.#', '', $fileObject->getDefaultExtension());
			}
			catch(\Exception $e) {
				$filename = basename($file);
				$extension = substr($filename, strrpos($filename, '.') + 1);
			}
			// get minifier for this extension
			$minifier = $this->getMinifier($extension);
			if(!$minifier) {
				throw new Exception("No minifier exist for extension '$extension'");
			}
			// get minified file
			$content = file_get_contents($file);
			$minified = $minifier->minify($content);
			/*var_dump(array('minified version' => $minified));*/
			// save it in cache
			$this->cache($cacheKey, $minified);
		}
		return $this->getCache($cacheKey);
	}
	
	protected function getMinifier($extension)
	{
		$minifier = null;
		switch($extension) {
			case 'css':	$minifier = new MinifierCss(); break;
			case 'js':	$minifier = new MinifierJs(); break;
		}
		return $minifier;
	}
	
	protected function getCacheKey($file)
	{
		return md5_file($file);
	}
	
	protected function isCached($key)
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
	
	protected function getCache($key)
	{
		$file = null;
		// if we can use the cache
		if($this->getCacheManagerService()) {
			// get the cache
			$cache = $this->getCacheManagerService()->getCache(self::$cacheId);
			// if cache exists
			if(!empty($cache)) {
				// if the cache contains the value
				if($cache->test($key)) {
					$file = $cache->load($key);
				}
			}
		}
		return $file;
	}
	
	protected function cache($key, $content)
	{
		// if we can use the cache
		if($this->getCacheManagerService()) {
			// get the cache
			$cache = $this->getCacheManagerService()->getCache(self::$cacheId);
			// if cache exists
			if(!empty($cache)) {
				// save the acl to the cache
				if(!$cache->save($content, $key)) {
					throw new Exception("Can't write to cache '" . self::$cacheId . "'.\n".var_export(array('id' => $key, 'content' => $content), true));
				}
			}
		}
	}
}