<?php

/*
 * will build all classes dependencies
 * format is :
 * array(
 * 		'classname1' => array(
 * 			'_hierarchy' => array(
 * 				'classname45' => ...
 * 			),
 * 			'_usage' => array(
 * 				'classname34' => ...
 * 			)
 * 		),
 * 		'classname2' => ...
 * )
 */


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
    'libfile|l-s'   => 'Libraries class file to be analysed',
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
if (isset($opts->l)) {
    $libfile = $opts->l;
    if (empty($libfile)) {
        echo "Empty libraries class file provided; aborting" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
    if(!file_exists($libfile)) {
    	echo "Libraries class file provided ($libfile) doesn't exist; aborting" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
}
else {
	echo "You must provide a libraries class file; aborting" . PHP_EOL . PHP_EOL;
    echo $opts->getUsageMessage();
    exit(2);
}

$usingStdout = false;
$output = $path . DIRECTORY_SEPARATOR . '.dependencies.php';
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
            echo "Dependencies file already exists at '$output'," . PHP_EOL
                . "but 'overwrite' flag was not specified; aborting." . PHP_EOL 
                . PHP_EOL
                . $opts->getUsageMessage();
            exit(2);
        }
    }
}


if (!$usingStdout) {
    echo "Creating dependencies file in directory '$path'..." . PHP_EOL;
}

if(false === ($libs = include $libfile)) {
	echo "Unable to include library class file ($libfile); aborting" . PHP_EOL;
	exit(2);
}

use Symbiose\Component\Utils\ClassAnalyser;

// if we've got libs
if(!empty($libs)) {
	// classes to analyse
	$classes = array();
	// get classes from libs
	//foreach($libs as $lib) {
	//	$classes = array_merge($classes, $lib);
	//}
	$classes = $lib;
	echo "Info : there are " . count($classes) . " base classes to analyse !" . PHP_EOL;
	try {
    	function analyseClass($class, &$tree, $index, $depth = 0, &$analysedClasses, &$currentClasses, &$circulars) {
	    	if(!empty($class) && strrpos($class, '\\') !== 0) {
		    	// remove begining slashes
	    		$class = trim($class, '\\');
	    		
	    		//echo '[DEBUG] [depth: ' . str_pad($depth, 4) . '] Analysing class '  . $class . ' ...' . PHP_EOL;
		    	unset($tree[$index]);
	    		
		    	if(isset($currentClasses[$class])) {
		    		//echo '[DEBUG] circular reference ' . $class . PHP_EOL;
		    		$trace = array();
		    		$keep = false;
		    		foreach($currentClasses as $c => $t) {
		    			if($c == $class) {
		    				$keep = 0;
		    			}
		    			if($keep !== null) {
		    				$trace[$keep++] = $c;
		    			}
		    		}
		    		$trace[$keep] = $class;
		    		$circulars[] = $trace;
		    		/* if the circular reference is the following :
		    		 * 
		    		 * problem is when :
		    		 * - class A use class U (=> B will be placed before A)
		    		 * - class B extends A (1 should be place before B)
		    		 * 
		    		 * consider this :
		    		 * 1. A use E
		    		 * 2. E extends C
		    		 * 3. C use D
		    		 * 4. D extends E (! problem)
		    		 * 
		    		 * gives => AuE, E:C, CuD, D!
		    		 * keep E:C and D:E
		    		 * solution: AuD, D:E, E:C, C!
		    		 * resolution: replace the use statement of A use E with : use D
		    		*/
		    		// if the last class analysed extends this one
		    		//echo '[DEBUG] last analysed class type ' . end($currentClasses) . PHP_EOL;
		    		if(end($currentClasses) == '_hierarchy') {
		    			// send a an array containing the class replacement :
		    			// the current class => the class that extends it
		    			//echo '[DEBUG] last analysed class ' . key($currentClasses) . PHP_EOL;
		    			return array($class => key($currentClasses));
		    		}
		    		return;
		    	}
    			if(isset($analysedClasses[$class])) {
		    		$tree[$class] = $analysedClasses[$class];
		    		//echo '[DEBUG] using already analysed class ' . $class . PHP_EOL;
		    	}
		    	else {
	    			try {
			    		$classAnalyser = new ClassAnalyser($class);
		    			$dependencies = $classAnalyser->getDependencies(false);
		    			$listOfClasses = array_unique(array_merge($dependencies['_hierarchy'], $dependencies['_usage']));
			    	}
			    	catch (\ReflectionException $re) {
			    		echo '[DEBUG] [Exception] ' . $re->getMessage() . PHP_EOL;
			    		return;
			    	}
		    		$tree[$class] = $listOfClasses;
		    		if(!empty($tree[$class])) {
		    			// get the first class usage
		    			$classesUsage = array_diff($dependencies['_usage'], $dependencies['_hierarchy']);
		    			$firstClassOfUsage = !empty($classesUsage) ? reset($classesUsage) : null;
		    			$currentClasses[$class] = '_hierarchy';
			    		foreach($tree[$class] as $i => $c) {
			    			if($c == $firstClassOfUsage) {
			    				$currentClasses[$class] = '_usage';
			    			}
					    	$result = analyseClass($c, $tree[$class], $i, $depth + 1, $analysedClasses, $currentClasses, $circulars);
					    	
					    	// until the result is not an array (there is no replacement)
					    	while(is_array($result)) {
					    		//echo '[DEBUG] get array response :' . PHP_EOL;
					    		//print_r($result);
					    		//echo '[DEBUG] class to replace: ' . key($result) . PHP_EOL;
					    		// if this class is not the last analysed (before the cirecular reference)
					    		// and the last class analysed must be replaced
					    		if($class != reset($result) && $c == key($result)) {
					    			//echo 'Current state of the tree' . PHP_EOL;
					    			//print_r($tree[$class]);
					    			echo '[DEBUG] need to replace the current class ' . $c . ' with index ' . $i . ' by the class '. reset($result) . PHP_EOL;
					    			// remove bad current tree class
					    			unset($tree[$class][$c]);
					    			// replace the current by the one provided
					    			$c = reset($result);
					    			$tree[$class][$i] = $c;
					    			// re run the analysis
					    			$result = analyseClass($c, $tree[$class], $i, $depth + 1, $analysedClasses, $currentClasses, $circulars);
					    			//echo '[DEBUG] get result :' . PHP_EOL;
					    			//print_r($result);
					    			//echo 'Current state of the tree' . PHP_EOL;
					    			//print_r($tree[$class]);
					    		}
					    		// a replacement will happen
					    		// it means that loop will be rebuild
					    		// so interrupt it an tranfer the response
					    		else {
					    			unset($currentClasses[$class]);
					    			//echo '[DEBUG] unset the current class as being analysed' . PHP_EOL;
					    			//print_r($currentClasses);
					    			//die();
					    			return $result;
					    		}
					    	}
					    }
					    unset($currentClasses[$class]);
		    		}
			    	$analysedClasses[$class] = $tree[$class];
		    	}
	    	}
	    };
		// analysing classes
		$classes = array_unique($classes);
		$analysedClasses = array();
		$currentClassesAnalysed = array();
		$circulars = array();
		foreach($classes as $index => $c) {
			analyseClass($c, $classes, $index, 0, $analysedClasses, $currentClassesAnalysed, $circulars);
		}
		//print_r($classes); exit(0);
		
		// Create a file with the dependencies
		// Stupid syntax highlighters make separating < from PHP declaration necessary
		$content = '<' . "?php\n"
		         . 'return ' . var_export((array) $classes, true) . ';';
		
		// Write the contents to disk
		file_put_contents($output, $content);
		
		if (!$usingStdout) {
		    echo "Wrote dependencies file to '" . realpath($output) . "'" . PHP_EOL;
		}
		
		echo "Info : there were " . count($analysedClasses) . " classes analysed !" . PHP_EOL;
		
		if(!empty($circulars)) {
			echo "Circulars references were found." . PHP_EOL;
			//print_r($circulars);
			$content = '<' . "?php\n"
		         . 'return ' . var_export((array) $circulars, true) . ';';
		    // Write the contents to disk
		    $circularsFile = str_replace('.php', '-circulars.php', $output);
			file_put_contents($circularsFile, $content);
			
			if (!$usingStdout) {
			    echo "Wrote circulars references file to '" . realpath($circularsFile) . "'" . PHP_EOL;
			}
			
			echo "Info : there were " . count($circulars) . " circulars references detected !" . PHP_EOL;
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
    	//var_dump($e);
    	echo '---------' . PHP_EOL . 'abording' . PHP_EOL;
    	exit(1);
    }
}