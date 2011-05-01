<?php

namespace Symbiose\Framework;

class Exception extends \RuntimeException {}
class InvalidArgumentException extends \InvalidArgumentException {}

class Bootstrap
{
	protected $libraryPath;
	protected $debug;
	
	public function __invoke($libraryPath, $debug = false)
	{
		// lib path
		if(!is_dir($libraryPath)) {
			throw new InvalidArgumentException("Library path '$libraryPath' doesn't exists");
		}
		$this->libraryPath = $libraryPath;
		
		// debug
		$this->debug = $debug;
		
		// initialise class loader
		$this->_initClassLoader();
		
		// initialise cache manager files
		$this->_initCacheManager();
	}
	
	protected function _initClassLoader()
	{
		// get class loader files
		require $this->libraryPath . DS . 'Symbiose' . DS . 'Component' . DS . 'ClassLoader' . DS . 'Exception' . DS . 'ClassLoaderException.php';
		require $this->libraryPath . DS . 'Symbiose' . DS . 'Component' . DS . 'ClassLoader' . DS . 'ClassLoader.php';
		// instantiate
		$classLoader = new \Symbiose\Component\ClassLoader\ClassLoader(/*IS_DEBUG*/false, /*IS_DEBUG ? $fileLogger : null*/null, $fileCache);
		// set namespaces and prefixes
		$classLoader->setNamespaces(array(
			'Falcon'		=>	$this->libraryPath,
			'Symfony'		=>	$this->libraryPath . DS . 'Symfony' . DS .'src'
		));
		$classLoader->setPrefixes(array(
			'Zend_'			=>	$this->libraryPath
		));
		// register class loader
		$classLoader->register();
	}
	
	protected function _initCacheManager()
	{
		// get cache manager files
		$cacheManager = new \Symbiose\Component\Caching\CacheManager(CACHE_PATH, /*IS_DEBUG*/false);
	}
}