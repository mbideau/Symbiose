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
    'libraries|l-s'   => 'Libraries to analyse their usage',
	'application|a-s'   => 'Application to parse; if none provided, assumes current directory',
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
if (isset($opts->a)) {
    $path = $opts->a;
    if (!is_dir($path)) {
        echo "Invalid application directory provided" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
    $path = realpath($path);
}
if (isset($opts->l)) {
    $libs = explode(',', $opts->l);
    if (empty($libs)) {
        echo "Empty libraries list provided" . PHP_EOL . PHP_EOL;
        echo $opts->getUsageMessage();
        exit(2);
    }
}
else {
	echo "You must provide a library list" . PHP_EOL . PHP_EOL;
    echo $opts->getUsageMessage();
    exit(2);
}

$usingStdout = false;
$output = $path . DIRECTORY_SEPARATOR . '.libusage.php';
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
            echo "Library usage file already exists at '$output'," . PHP_EOL
                . "but 'overwrite' flag was not specified; aborting." . PHP_EOL 
                . PHP_EOL
                . $opts->getUsageMessage();
            exit(2);
        }
    }
}

$strip     = $path;

if (!$usingStdout) {
    echo "Creating library usage file for application in '$path'..." . PHP_EOL;
}

// build regex lib catcher
$regex = '#(' . implode('|', $libs) . ')(\\\\\w+|_\w+)*#';

// Get the ClassFileLocater, and pass it the library path
$l = new \Symbiose\Component\File\ExtensionFileLocater($path, array('.php', '.xml'));

// Iterate over each element in the path, and create a map of 
// classname => filename, where the filename is relative to the library path
$map    = new \stdClass;
$strip .= DIRECTORY_SEPARATOR;
iterator_apply($l, function() use ($l, $map, $strip, $regex){
    $file      = $l->current();
    $filename  = str_replace($strip, '', $file->getRealpath());
    if(strpos($filename, '.') === 0) {
    	return true;
    }
	$content = file_get_contents($file);
    
    if(preg_match_all($regex, $content, $matches)) {
    	$textMatches = $matches[0];
    	$numberofMatches = count($textMatches);
    	if($numberofMatches) {
	    	$libMatches = $matches[1];
    		$classMatches = $matches[2];
    		//echo 'file: ' . $filename . PHP_EOL;
	    	for($i = 0; $i < $numberofMatches; $i++) {
	    		//echo '   ' . $textMatches[$i] . PHP_EOL;
	    		$lib = $libMatches[$i];
	    		$class = $textMatches[$i];
	    		if(!empty($lib)) {
		    		if(!isset($map->{$lib}) || !is_array($map->{$lib})) {
		    			$map->{$lib} = array();
		    		}
		    		array_push($map->{$lib}, $class);
	    		}
	    	}
    	}
    }

    return true;
});

echo 'cleaning matches ...' . PHP_EOL;
foreach($libs as $lib) {
	if(!isset($map->{$lib}) || !is_array($map->{$lib})) {
		$map->{$lib} = array();
	}
	$map->{$lib} = array_unique($map->{$lib});
}

// Create a file with the class/file map.
// Stupid syntax highlighters make separating < from PHP declaration necessary
$content = '<' . "?php\n"
         . 'return ' . var_export((array) $map, true) . ';';

// Write the contents to disk
file_put_contents($output, $content);

if (!$usingStdout) {
    echo "Wrote library usage file to '" . realpath($output) . "'" . PHP_EOL;
}