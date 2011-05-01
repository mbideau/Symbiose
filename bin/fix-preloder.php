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
    'preloader|p-s'   => 'Preloader file to fix',
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
    echo "Creating test preloader file in directory '$path'..." . PHP_EOL;
}

if(false === ($classes = include $preloader)) {
	echo "Unable to include preloader file ($preloader); aborting" . PHP_EOL;
	exit(2);
}

if(!empty($classes)) {
	if(($pos = array_search('Doctrine\\ORM\\Mapping\\Driver\\AnnotationDriver')) !== false) {
		array_splice($classes, $pos, 0, 'Doctrine\\Common\\Annotations\\Annotation');
		
		// Create a file with the classes
		// Stupid syntax highlighters make separating < from PHP declaration necessary
		$content = '<' . "?php\n"
		         . 'return ' . var_export((array) $classes, true) . ';';
		
		// Write the contents to disk
		file_put_contents($output, $content);
		
		if (!$usingStdout) {
		    echo "Wrote preloader file to '" . realpath($output) . "'" . PHP_EOL;
		}
		
		echo "Info : there are " . count($classes) . " classes in the preloader !" . PHP_EOL;
	}
}