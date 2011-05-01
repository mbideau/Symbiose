<?php

// site root
$SITE_ROOT = dirname(__DIR__);
if (array_key_exists('PWD', $_SERVER)) {
    $SITE_ROOT = dirname($_SERVER['PWD']);
}
// Library path
$LIBRARY_PATH = "$SITE_ROOT/library";
// Symbiose path
$SYMBIOSE_PATH = "$LIBRARY_PATH/Symbiose/src";

// empty the include path
set_include_path('');

// class autoloader
include "$LIBRARY_PATH/ZF2-master/library/Zend/Loader/ClassMapAutoloader.php";
use Zend\Loader\ClassMapAutoloader;
$classMapAutoloader = new ClassMapAutoloader();
$classMapAutoloader->registerAutoloadMap(array(
	'Zend\Console\Getopt' => "$LIBRARY_PATH/ZF2-master/library/Zend/Console/Getopt.php",
	'Symbiose\Component\Optimization\PHPStripper' => "$SYMBIOSE_PATH/Symbiose/Component/Optimization/PHPStripper.php",
));
$classMapAutoloader->register();
