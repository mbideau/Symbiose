<?php
namespace Falcon\Site\Component\ClassLoader;

use Falcon\Site\Component\ClassLoader\Exception\ClassLoaderException as Exception
;

class ClassCompiler
{
	protected $namespaces = array();
	protected $prefixes = array();
	protected $classList = array();
	
	public function __construct(array $namespaces = array(), array $prefixes = array(), array $classList = array())
	{
		$this->namespaces = $namespaces;
		$this->prefixes = $prefixes;
		$this->classList = $classList;
	}
	
	public function setNamespaces(array $list)
	{
		$this->namespaces = $namespaces;
		return $this;
	}
	
	public function setPrefixes(array $list)
	{
		$this->prefixes = $prefixes;
		return $this;
	}
	
	public function setClassList(array $list)
	{
		$this->classList = $list;
		return $this;
	}
	
	public function compile($fileOutput)
	{
		if(empty($fileOutput)) {
			throw new Exception("File output is empty");
		}
		// get ordered classes content
		$content = $this->getOrderedClassesContent();
		
		// creating parent dir of output file (if needed)
		if(!is_dir(dirname($fileOutput))) {
			mkdir(dirname($fileOutput), 0777, true);
		}
		
		self::writeFile($fileOutput, self::stripComments('<?php '.$content));
	}
	
	protected function getOrderedClassesContent()
	{
		if(empty($this->classList)) return '';
		
		// order classes according to their dependency
		$orderedClasses = $this->getOrderedClasses();
		
		$content = '';
		foreach($orderedClasses as $class) {
			if(!class_exists($class) && !interface_exists($class)) {
				throw new \InvalidArgumentException(sprintf('Unable to load class "%s"', $class));
			}

			$r = new \ReflectionClass($class);
			$files[] = $r->getFileName();

			$content .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($r->getFileName()));
		}
		
		return $content;
	}
	
	protected function getOrderedClasses()
	{
		$orderedClasses = $this->classList;
		
		if(empty($orderedClasses)) return array();
		
		$classesToOrder = $orderedClasses;
		// while there is class to order
		while(list($key, $class) = each($classesToOrder)) {
			// get its dependencies
			$dependencies = $this->getDependencies($class);
			// if it has dependencies
			if(!empty($dependencies)) {
				//@todo
				
			}
			// no dependencies
			else {
				// just add it to ordered classes
				$orderedClasses[] = $class;
				// removes it from the class to order
				unset($classesToOrder[$key]);
			}
		}
		
		return $orderedClasses;
	}
	
	public function getDependenciesTree($class, &$classOri = null, &$depth = 0)
	{
		if(!$classOri) {
			$classOri = $class;
		}
		if($depth >= 5) return;
		echo str_pad('', $depth).$class."\n";
		if(!empty($class)) {
			// get its dependencies
			$dependencies = $this->getDependencies($class);
			// if it has dependencies
			if(!empty($dependencies)) {
				//var_dump($dependencies);
				// for each of them, get their dependencies tree
				foreach($dependencies as $depClass) {
					if($cla)
					$this->getDependenciesTree($depClass, $classOri, ++$depth);
				}
			}
		}
	}
	
	public function getDependenciesTree_old($class, &$depth = 0)
	{
		++$depth;
		if($depth >= 5) {
			return 'max depth reached';
		}
		echo "* Loading class '$class' ...\n";
		$tree = $class;
		if(!empty($class)) {
			// get its dependencies
			$dependencies = $this->getDependencies($class);
			// if it has dependencies
			if(!empty($dependencies)) {
				$tree = array($class => array());
				// for each of them, get their dependencies tree
				foreach($dependencies as $depClass) {
					echo "   + Getting dependencies tree for class '$depClass' ...\n";
					$tree[$class][] = $this->getDependenciesTree($depClass, $depth);
				}
			}
		}
		echo "* Tree returned for class '$class' is\n".print_r($tree, true)."\n";
		return $tree;
	}
	
	public function getDependencies($class)
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
	
	public function getRequireStatements($class)
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
							&& ($t[0] == T_REQUIRE || $t[0] == T_REQUIRE_ONCE)
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
	
	public function getClassFile($class)
	{
		$file = null;
		if(!empty($class)) {
			// namespaced class name
	        if(false !== ($pos = strripos($class, '\\'))) {
	            $namespace = substr($class, 0, $pos);
	            foreach($this->namespaces as $ns => $dir) {
	    			if(0 === strpos($namespace, $ns)) {
	                    $class = substr($class, $pos + 1);
	                    // architecture respected
	                    $testFile = $dir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
	                    if(is_readable($testFile)) {
	                    	$file = $testFile;
	                    }
	                    // direct folder
	                    else {
	                    	$testFile = $dir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
		                    if(is_readable($testFile)) {
		                    	$file = $testFile;
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
	                    $testFile = $dir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
	                    if (is_readable($testFile)) {
	                    	$file = $testFile;
	                    }
	                    break;
	                }
	            }
	        }
		}
		return $file;
	}
	
	static protected function writeFile($file, $content)
	{
		$tmpFile = tempnam(dirname($file), basename($file));
		if(!$fp = @fopen($tmpFile, 'wb')) {
			die(sprintf('Failed to write cache file "%s".', $tmpFile));
		}
		@fwrite($fp, $content);
		@fclose($fp);

		if($content != file_get_contents($tmpFile)) {
			die(sprintf('Failed to write cache file "%s" (cache corrupted).', $tmpFile));
		}

		@rename($tmpFile, $file);
		chmod($file, 0644);
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
        $output = preg_replace(array('/\n\s*\{/', '/\n\s*\}/'), array(' {', ' }'), $output);

        return $output;
    }
}