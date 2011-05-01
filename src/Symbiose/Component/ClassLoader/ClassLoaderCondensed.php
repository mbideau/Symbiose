<?php

namespace Symbiose\Component\ClassLoader;

// we don't use the USE statement for cache manager to prevent cycle requirements
// instead we check that the methods we needs, exists for the cache manager (in the set method)
use Symbiose\Component\ClassLoader\Exception\ClassLoaderException as Exception;

class ClassLoaderCondensed
{
	protected $tempCache = array();
	
	protected $namespaces = array();
    protected $prefixes = array();

    protected $cacheManager;
    protected $debug;
    
    protected $envName;
    
    public function __construct(array $compiledClassesFiles = array())
	{
		// load compiled classes (if given)
		if(count($compiledClassesFiles)) {
			foreach($compiledClassesFiles as $file) {
				require $file;
			}
		}
	}
    
	public function getTempCache()
	{
		return $this->tempCache;
	}
	
	protected function _getCacheId($id)
	{
		return $this->envName . '-' . $id;
	}
	
	public function loadMergedClasses()
    {
    	if($this->cacheManager && $this->cacheManager->hasCache(self::$cacheId)) {
    		$cache = $this->cacheManager->getCache(self::$cacheId);
    		// load prefixed classes
    		$prefixedId = $this->_getCacheId(self::$mergedPrefixedClassesCacheId);
    		if($cache->test($prefixedId)) {
	    		$prefixedClassesFile = $cache->getBackend()->getFile($prefixedId);
    			if(is_readable($prefixedClassesFile)) {
	    			require $prefixedClassesFile;
	    		}
    		}
	    	// load namespaced classes
	    	$namespacedId = $this->_getCacheId(self::$mergedNamespacedClassesCacheId);
	    	if($cache->test($namespacedId)) {
		    	$namespacedClassesFile = $cache->getBackend()->getFile($namespacedId);
		    	if(is_readable($namespacedClassesFile)) {
		    		require $namespacedClassesFile;
		    	}
	    	}
    	}
    }
    
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    public function getPrefixes()
    {
        return $this->prefixes;
    }

    /**
     * Registers an array of namespaces
     *
     * @param array $namespaces An array of namespaces (namespaces as keys and locations as values)
     */
    public function registerNamespaces(array $namespaces)
    {
        $this->namespaces = array_merge($this->namespaces, $namespaces);
    }

    /**
     * Registers a namespace.
     *
     * @param string $namespace The namespace
     * @param string $path      The location of the namespace
     */
    public function registerNamespace($namespace, $path)
    {
        $this->namespaces[$namespace] = $path;
    }

    /**
     * Registers an array of classes using the PEAR naming convention.
     *
     * @param array $classes An array of classes (prefixes as keys and locations as values)
     */
    public function registerPrefixes(array $classes)
    {
        $this->prefixes = array_merge($this->prefixes, $classes);
    }

    /**
     * Registers a set of classes using the PEAR naming convention.
     *
     * @param string $prefix The classes prefix
     * @param string $path   The location of the classes
     */
    public function registerPrefix($prefix, $path)
    {
        $this->prefixes[$prefix] = $path;
    }

    /**
     * Registers this instance as an autoloader.
     */
    public function register()
    {
    	// throw exceptions
        spl_autoload_register(array($this, 'loadClass'), true, true);
    }
	
    protected function isNamespace($class)
    {
    	return (strpos($class, '\\') !== false);
    }
    
	protected function isPrefixed($class)
    {
    	return (strpos($class, '\\') === false);
    }
    
    protected function getRequireStatements($classFile)
    {
    	$dependencies = array();
		if(!empty($class)) {
			// get class file
			$classFile = $this->getClassFile($class);
			if(!empty($classFile)) {
				// tokenise the file
				$tokens = token_get_all(file_get_contents($classFile));
				foreach($tokens as $t) {
					if(is_array($t) && !empty($t)) {
						if(
							array_key_exists(0, $t)
							&& $t[0] == T_USE
						) {
							$className = '';
							while(
								($next = next($tokens))
								&& (is_array($next) || (is_string($next) && $next != ';'))
							) {
								if(is_array($next) && array_key_exists(1, $next)) {
									$className .= $next[1];
								}
								elseif(is_string($next) && !preg_match('#[a-zA-Z0-9_]#', $next)) {
									$dependencies[] = $className;
									$className = '';
								}
							}
							$dependencies[] = $className;
						}
					}
				}
			}
		}
		array_walk($dependencies, create_function('&$v, $k', '
			$v = preg_replace("#(^use\s+|;$|\s+as\s+(.*)$)#", "", trim($v));
		'));
		return $dependencies;
    }
    
    protected function updateMergedClasses()
    {
    	if($this->cacheManager) {
	    	!$this->logger ?: $this->logger->log("Updating merged classes ...");
	    	// foreach classes of the temp cacheManager
	    	if(!empty($this->tempCache)) {
	    		!$this->logger ?: $this->logger->log("Cache is not empty ...");
	    		$mergedPrefixedClassesContent = '';
	    		$mergedNamespacedClassesContent = '';
	    		foreach($this->tempCache as $class => $file) {
	    			// get class file content
	    			if(($content = @file_get_contents($file)) === false) {
	    				throw new Exception("Failed to get content of file '$cacheFile'");
	    			}
	    			// get require statements in the content
	    			
	    			// foreach statements
	    				// if the class exists
	    					// cache its content
	    			
	    			// if it is prefixed
	    			if($this->isPrefixed($class)) {
	    				!$this->logger ?: $this->logger->log("Adding class '$class' to prefix content ...");
	    				$mergedPrefixedClassesContent .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', $content);
	    			}
	    			// namespaced
	    			else {
	    				!$this->logger ?: $this->logger->log("Adding class '$class' to namespace content ...");
	    				$mergedNamespacedClassesContent .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', $content);
	    			}
	    		}
	    		if($mergedPrefixedClassesContent != '') {
	    			!$this->logger ?: $this->logger->log("Caching prefix content ...");
		    		$content = self::stripComments($mergedPrefixedClassesContent);
		    		// if cache file doesn't exist
		    		if(!$this->cacheManager->hasCacheFile(self::$mergedPrefixedClassesFilename)) {
		    			// add php tag
		    			$content = "<?php $content";
		    		}
		    		// append class content to cache file
		    		$this->cacheManager->writeCacheFile($content, true, true, self::$mergedPrefixedClassesFilename);
	    		}
	    		if($mergedNamespacedClassesContent != '') {
		    		!$this->logger ?: $this->logger->log("Caching namespace content ...");
	    			$content = self::stripComments($mergedNamespacedClassesContent);
	    			// if cache file doesn't exist
		    		if(!$this->cacheManager->hasCacheFile(self::$mergedNamespacedClassesFilename)) {
		    			// add php tag
		    			$content = "<?php $content";
		    		}
		    		// append class content to cache file
		    		$this->cacheManager->writeCacheFile($content, true, true, self::$mergedNamespacedClassesFilename);
	    		}
	    	}
    	}
    }
    /*
	public function __destruct()
	{
		// if in debug mode
		if($this->debug) {
			// update merged classes
			try {
				$this->updateMergedClasses();
			}
			catch(\Exception $exception) {
				error_log(sprintf("ClassLoader::%s : Uncaught PHP Exception %s: '%s' at %s line %s\nTrace: %s", __FUNCTION__, get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString()));
			}
		}
	}
	*/
	public function setCacheManager($cacheManager)
	{
		$this->cacheManager = $cacheManager;
		$this->_checkCacheManagerMethods();
		return $this;
	}
	
	protected function _checkCacheManagerMethods()
	{
		if(!empty($this->cacheManager)) {
			if(!is_object($this->cacheManager)) {
				throw new Exception("Cache Manager must be an object ('" . gettype($this->cacheManager) . "' given)");
			}
			foreach(array('hasCache', 'getCache') as $methodName) {
				if(!method_exists($this->cacheManager, $methodName)) {
					throw new Exception("Cache manager class '" . get_class($this->cacheManager) . "' must have the method '$methodName'");
				}
			}
		}
	}
	
	protected function getClassFileFromCache($class)
	{
		return array_key_exists($class, $this->tempCache) ? $this->tempCache[$class] : null;
	}
	
	/**
     * Set an array of namespaces
     *
     * @param array $namespaces An array of namespaces (namespaces as keys and locations as values)
     */
    public function setNamespaces(array $namespaces)
    {
        $this->namespaces = $namespaces;
    }
    
	/**
     * Set an array of classes using the PEAR naming convention.
     *
     * @param array $classes An array of classes (prefixes as keys and locations as values)
     */
    public function setPrefixes(array $classes)
    {
        $this->prefixes = $classes;
    }
    
	/**
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     */
    public function loadClass($class)
    {
    	try {
			$classOri = $class;
	    	!$this->logger ?: $this->logger->log("Loading class '$classOri' ...");
	    	// try to get it from the temp cache
	    	$file = $this->getClassFileFromCache($class);
	    	if(!empty($file)/* && file_exists($file)*/) {
	    		require_once $file;
	    	}
	    	else {
	    		// namespaced class name
		        if(($pos = strripos($class, '\\')) !== false) {
		            $namespace = substr($class, 0, $pos);
		            foreach ($this->namespaces as $ns => $dir) {
		    			if (0 === strpos($namespace, $ns)) {
		                    $class = substr($class, $pos + 1);
		                    // architecture respected
		                    $file = $dir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
		                    if(is_readable($file)) {
		                    	require $file;
		                        $this->tempCache[$classOri] = $file;
		                        !$this->logger ?: $this->logger->log("Caching class '$classOri'");
		                    }
		                    // direct folder
		                    else {
		                    	$file = $dir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
			                    if(is_readable($file)) {
			                    	require $file;
			                        $this->tempCache[$classOri] = $file;
			                        !$this->logger ?: $this->logger->log("Caching class '$classOri'");
			                    }
		                    }
		                    break;
		                }
		            }
		        }
		        // PEAR-like class name
		        else {
		            foreach ($this->prefixes as $prefix => $dir) {
		                if (0 === strpos($class, $prefix)) {
		                    $file = $dir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
		                    if (is_readable($file)) {
		                    	require $file;
		                    	$this->tempCache[$classOri] = $file;
		                    	!$this->logger ?: $this->logger->log("Caching class '$classOri'");
		                    }
		                    break;
		                }
		            }
		        }
	    	}
		}
		catch(\Exception $e) {
			var_dump("Uncaught exception in class loader.\n[" . get_class($e) . "]\n   Message:\n" . $e->getMessage() . "\n   Trace:\n" . $e->getTraceAsString()); die();
		}
    }
    
    /**
     * Removes comments from a PHP source string.
     *
     * We don't use the PHP php_strip_whitespace() function
     * as we want the content to be readable and well-formatted.
     *
     * @param string $source A PHP string
     *
     * @return string The PHP string with the comments removed
     */
    static public function stripComments($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (!in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= $token[1];
            }
        }

        // replace multiple new lines with a single newline
        $output = preg_replace(array('/\s+$/Sm', '/\n+/S'), "\n", $output);

        // reformat {} "a la python"
        //$output = preg_replace(array('/\n\s*\{/', '/\n\s*\}/'), array(' {', ' }'), $output);
        $output = preg_replace('/\n\s*\{/', ' {', $output);

        return $output;
    }
}