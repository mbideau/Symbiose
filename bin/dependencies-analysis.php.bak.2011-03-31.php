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
	foreach($libs as $lib) {
		$classes = array_merge($classes, $lib);
	}
	echo "Info : there are " . count($classes) . " base classes to analyse !" . PHP_EOL;
	try {
    	function analyseClass($class, &$tree, $index, $depth = 0, &$analysedClasses, &$currentClasses, &$circulars) {
	    	if(!empty($class) && strrpos($class, '\\') !== 0) {
		    	// remove begining slashes
	    		$class = trim($class, '\\');
	    		
	    		//echo '[DEBUG] [depth: ' . str_pad($depth, 4) . '] Analysing class '  . $class . ' ...' . PHP_EOL;
		    	unset($tree[$index]);
	    		
		    	if(array_key_exists($class, $currentClasses)) {
		    		//echo '[DEBUG] circular reference ' . $class . ' with classe of '. PHP_EOL;
		    		$trace = array();
		    		$keep = false;
		    		foreach($currentClasses as $c => $i) {
		    			if($c == $class) {
		    				$keep = 0;
		    			}
		    			if($keep !== null) {
		    				$trace[$keep++] = $c;
		    			}
		    		}
		    		$trace[$keep] = $class;
		    		$circulars[] = $trace;
		    		return;
		    	}
    			if(array_key_exists($class, $analysedClasses)) {
		    		$tree[$class] = $analysedClasses[$class];
		    		//echo '[DEBUG] using already analysed class ' . $class . PHP_EOL;
		    	}
		    	else {
	    			try {
			    		$classAnalyser = new ClassAnalyser($class);
		    			$dependencies = $classAnalyser->getDependencies(false);
		    			//$listOfClasses = array_unique(array_merge($dependencies['_hierarchy'], $dependencies['_usage']));
		    			$listOfClasses = array_unique($dependencies['_hierarchy']);
		    			if(in_array('Doctrine\\ORM\\Mapping\\Driver\\DoctrineAnnotations', $listOfClasses)) {
		    				print_r($listOfClasses); die();
		    			}
			    	}
			    	catch (\ReflectionException $re) {
			    		echo '[DEBUG] [Exception] ' . $re->getMessage() . PHP_EOL;
			    		$listOfClasses = array();
			    		return;
			    	}
		    		$tree[$class] = $listOfClasses;
		    		if(!empty($tree[$class])) {
			    		$currentClasses[$class] = $depth;
		    			foreach($tree[$class] as $i => $c) {
				    		analyseClass($c, $tree[$class], $i, $depth + 1, $analysedClasses, $currentClasses, $circulars);
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
			echo "circulars references found :" . PHP_EOL;
			print_r($circulars);
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