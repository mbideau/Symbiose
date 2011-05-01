<?php

if(!isset($argv[1])) {
	echo "You must provide autoloader file as first argument; aborting" . PHP_EOL;
	exit(2);
}
$autoloadFile = $argv[1];
array_shift($argv);

if (false === include($autoloadFile)) {
	echo "Unable to include autoloader file ($autoloadFile); aborting" . PHP_EOL;
	exit(2);
}

$rules = array(
    'help|h'        => 'Get usage message',
    'preloader|p-s' => 'Preloader file to test',
	'libfile|l-s'   => 'Lib file returning an array of pathes to libraries directory',
	'classmap|c'	=> "Generate a classmap instead of merging file's content",
    'output|o-s'    => 'Where to write autoload file; if not provided, assumes ".classmap.php" in library directory',
    'overwrite|w'   => 'Whether or not to overwrite existing autoload file',
);

try {
    $opts = new Zend\Console\Getopt($rules);
    $opts->parse();
} catch (Zend\Console\Getopt\Exception $e) {
    echo $e->getUsageMessage();
    exit(2);
}

if ($opts->getOption('h')) {
    echo $opts->getUsageMessage();
    exit();
}

$path = '.';
if (array_key_exists('PWD', $_SERVER)) {
    $path = $_SERVER['PWD'];
}
if (isset($opts->p)) {
    $preloader = $opts->p;
    if (empty($preloader)) {
        echo "Empty preloader file provided; aborting" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
    if(!file_exists($preloader)) {
    	echo "Preloader file provided ($preloader) doesn't exist; aborting" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
}
else {
	echo "You must provide a preloader file; aborting" . PHP_EOL . PHP_EOL;
    echo $opts->getUsageMessage();
    exit(2);
}
if (isset($opts->l)) {
    $libfile = $opts->l;
    if (empty($libfile)) {
        echo "Empty lib file provided; aborting" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
    if(!file_exists($libfile)) {
    	echo "Lib file provided ($libfile) doesn't exist; aborting" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
}
else {
	echo "You must provide a lib file; aborting" . PHP_EOL . PHP_EOL;
    echo $opts->getUsageMessage();
    exit(2);
}

$classmap = false;
if (isset($opts->c)) {
    $classmap = true;
}

$usingStdout = false;
$output = $path . DIRECTORY_SEPARATOR . '.tested-preloader.php';
if (isset($opts->o)) {
    $output = $opts->o;
    if ('-' == $output) {
        $output = STDOUT;
        $usingStdout = true;
    } elseif (!is_writeable(dirname($output))) {
        echo "Cannot write to '$output'; aborting." . PHP_EOL
            . PHP_EOL
            . $opts->getUsageMessage();
        exit(2);
    } elseif (file_exists($output)) {
        if (!$opts->getOption('w')) {
            echo "Preloader file already exists at '$output'," . PHP_EOL
                . "but 'overwrite' flag was not specified; aborting." . PHP_EOL 
                . PHP_EOL
                . $opts->getUsageMessage();
            exit(2);
        }
    }
}


if (!$usingStdout) {
    echo "Creating test preloader file in directory '$path'..." . PHP_EOL;
}

if(false === ($classes = include $preloader)) {
	echo "Unable to include preloader file ($preloader); aborting" . PHP_EOL;
	exit(2);
}

if(false === ($libs = include $libfile)) {
	echo "Unable to include lib file ($libfile); aborting" . PHP_EOL;
	exit(2);
}

use Symbiose\Component\Optimization\PHPStripper;

// if we've got classes
if(!empty($classes) && !empty($libs)) {
	try {
		$files = $libs['files'];
		$libs = $libs['libs'];
		
		// merged content
		$contentPrefixed = '';
		$contentNamspaced = '';
		
		// class map
		$map = array();
		
		$classType = null;
		
		foreach($classes as $c) {
			// if it is a file inclusion
			if(is_array($c) && array_key_exists('file', $c) && array_key_exists('type', $c)) {
				$file = $c['file'];
				$type = $c['type'];
				if(!file_exists($file)) {
					throw new \RuntimeException("File inclued doesn't exists ($file)");
				}
				elseif($type != 'namespaced' && $type != 'prefixed') {
					throw new \RuntimeException("File inclued has invalid type ('$type' for '$file')");
				}
				else {
					// get class file content
			    	$fileContent = file_get_contents($file);
			    	
			    	// strip comments only if there is no code capture and the class is not an entity
					if(strpos($fileContent, '<<<EOF') === false) {
						$fileContent = PHPStripper::stripComments($fileContent);
					}
					
					// remove php tags
					$fileContent = preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', $fileContent);
			
					if($type == 'prefixed') {
						$contentPrefixed .= $fileContent . "\n";
					}
					else {
						$contentNamspaced .= $fileContent . "\n";
					}
				}
			}
			// class inclusion
			else {
				//echo '[DEBUG] testing class ' . $c . ' ...' . PHP_EOL;
				// namespaced
				if(strpos($c, '\\') !== false) {
					$classType = 'namespaced';
					$lib = strstr($c, '\\', true);
					if(!array_key_exists($lib, $libs)) {
						foreach($libs as $l => $ignored) {
							if(strpos($c, $l) === 0) {
								$lib = $l;
							}
						}
					}
					//echo '[DEBUG]    lib is ' . $lib . PHP_EOL;
					$classFile = $libs[$lib] . '/' . str_replace('\\', '/', $c) . '.php';
					if(!file_exists($classFile) && file_exists($libs[$lib] . '/' . substr(strrchr($c, '\\'), 1) . '.php')) {
						$classFile = $libs[$lib] . '/' . substr(strrchr($c, '\\'), 1) . '.php';
					}
				}
				// prefixed
				else {
					$classType = 'prefixed';
					// if in a subpackage
					if(strpos($c, '_') !== false) {
						$lib = strstr($c, '_', true) . '_';
						if(!array_key_exists($lib, $libs) && array_key_exists($c, $files)) {
							$classFile = $files[$c];
						}
						else {
							//echo '[DEBUG]    lib is ' . $lib . PHP_EOL;
							$classFile = $libs[$lib] . '/' . str_replace('_', '/', $c) . '.php';
						}
					}
					// if csstidy or jsmin
					elseif($c == 'csstidy' || $c == 'JSMin') {
						$lib = $c;
						if(!array_key_exists($lib, $libs) && array_key_exists($c, $files)) {
							$classFile = $files[$c];
						}
						else {
							//echo '[DEBUG]    lib is ' . $lib . PHP_EOL;
							$classFile = $libs[$lib] . '/' . str_replace('_', '/', $c) . '.php';
						}
					}
					// else do not test
					else {
						//echo '[DEBUG]    skiping' . PHP_EOL;
						continue;
					}
				}
				if(!file_exists($classFile)) {
					throw new \RuntimeException("File of class doesn't exists ($classFile)");
				}
				else {
					require_once $classFile;
				}
				try {
					$rlc = new \ReflectionClass($c);
				}
				catch (\ReflectionException $re) {
		    		echo '[DEBUG] [Exception] ' . $re->getMessage() . PHP_EOL;
		    	}
		    	
		    	if($classmap) {
		    		$map[$c] = strstr($classFile, '/library');
		    	}
		    	else {
			    	// get class file content
			    	$fileContent = file_get_contents($classFile);
			    	
			    	// strip comments only if there is no code capture and the class is not an entity
					if(strpos($fileContent, '<<<EOF') === false && strpos($c, 'Entities\\') !== 0) {
						$fileContent = PHPStripper::stripComments($fileContent);
					}
					
					// remove php tags
					$fileContent = preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', $fileContent);
			
					if($classType == 'prefixed') {
						$contentPrefixed .= $fileContent . "\n";
					}
					else {
						$contentNamspaced .= $fileContent . "\n";
					}
		    	}
			}
		}
		
		if($classmap) {
			// Create a file with the class/file map.
			// Stupid syntax highlighters make separating < from PHP declaration necessary
			$content = '<' . "?php\n"
			         . 'return ' . str_replace(" => '", " => __DIR__ . '/..", var_export((array) $map, true)) . ';';
			
			// Write the contents to disk
			file_put_contents($output, $content);
			
			if (!$usingStdout) {
			    echo "Wrote preloader class map file to '" . realpath($output) . "'" . PHP_EOL;
			}
		}
		else {
			//echo "Removing require statements ..." . PHP_EOL;
			$contentPrefixed = preg_replace("#\n\s*require(_once)?(\s+|\s*\()('|\"|__|dirname).*;#", "\n", $contentPrefixed);
			$contentNamspaced = preg_replace("#\n\s*require(_once)?(\s+|\s*\()('|\"|__|dirname).*;#", "\n", $contentNamspaced);
			
			// Write the contents to disk
			$filePrefixed = str_replace('.php', '-prefixed.php', $output);
			$fileNamespaced = str_replace('.php', '-namespaced.php', $output);
			file_put_contents($filePrefixed, "<?php $contentPrefixed");
			file_put_contents($fileNamespaced, "<?php $contentNamspaced");
			
			if (!$usingStdout) {
			    echo "Wrote test preloader files to \n'" . realpath($filePrefixed) . "' \n'" . realpath($fileNamespaced) . "'" . PHP_EOL;
			}
		}
	}
    catch(\Exception $e) {
    	echo '---------' . PHP_EOL . '  ERROR' . PHP_EOL . '---------' . PHP_EOL;
    	print_r(array(
    		'class' => get_class($e),
    		'message' => $e->getMessage(),
    		'trace' => $e->getTraceAsString(),
    		'file' => $e->getFile(),
    		'line' => $e->getLine()
    	));
    	echo '---------' . PHP_EOL . 'abording' . PHP_EOL;
    	exit(1);
    }
}