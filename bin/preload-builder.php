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
    'depfile|d-s'   => 'Dependencies file used to sort classes',
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
if (isset($opts->d)) {
    $depfile = $opts->d;
    if (empty($depfile)) {
        echo "Empty dependencies file provided; aborting" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
    if(!file_exists($depfile)) {
    	echo "Dependencies file provided ($depfile) doesn't exist; aborting" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
}
else {
	echo "You must provide a dependencies file; aborting" . PHP_EOL . PHP_EOL;
    echo $opts->getUsageMessage();
    exit(2);
}

$usingStdout = false;
$output = $path . DIRECTORY_SEPARATOR . '.preloader.php';
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
    echo "Creating preloader file in directory '$path'..." . PHP_EOL;
}


if(false === ($classes = include $depfile)) {
	echo "Unable to include dependencies file ($depfile); aborting" . PHP_EOL;
	exit(2);
}


// if we've got classes
if(!empty($classes)) {
	try {
		function flattern($array, $type = null) {
	        $flat = array();
	        foreach($array as $key => $value) {
	                if(is_array($value)) {
	                        if(isset($value[$type])) {
	                        	$value = $value[$type];
	                        }
	                        $flatBack = flattern($value, $type);
	                		if($type != null && is_array($flatBack) && isset($flatBack[$type])) {
	                			$flat = array_merge($flat, $flatBack[$type]);
	                		}
	                		else {
	                			$flat = array_merge($flat, $flatBack);
	                		}
	                        array_push($flat, $key);
	                }
	                else {
	                        array_push($flat, $value);
	                }
	        }
	        return $flat;
		}
		$sortedHierarchy  = flattern($classes, '_hierarchy');
		$sortedHierarchy  = array_unique($sortedHierarchy);
		
		$sortedUsage  = flattern($classes);
		$sortedUsage  = array_unique($sortedUsage);
		
		$sorted = array_unique(array_merge($sortedHierarchy, $sortedUsage));
		
		// Create a file with the classes
		// Stupid syntax highlighters make separating < from PHP declaration necessary
		$content = '<' . "?php\n"
		         . 'return ' . var_export((array) $sorted, true) . ';';
		
		// Write the contents to disk
		file_put_contents($output, $content);
		
		if (!$usingStdout) {
		    echo "Wrote preloader file to '" . realpath($output) . "'" . PHP_EOL;
		}
		
		echo "Info : there are " . count($sorted) . " classes in the preloader !" . PHP_EOL;
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
