<?php

// site root
$SITE_ROOT = dirname(dirname(__FILE__));
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
include "$LIBRARY_PATH/ZF2-master/library/Zend/Loader/StandardAutoloader.php";
use Zend\Loader\StandardAutoloader;
$standardAutoloader = new StandardAutoloader(array(
	'namespaces' => array(
		'Application'		=>	"$LIBRARY_PATH/Application",
		'Zend'				=>	"$LIBRARY_PATH/ZF2-master/library/Zend",
		'Symbiose'			=>	"$SYMBIOSE_PATH/Symbiose",
		'Symfony'			=>	"$LIBRARY_PATH/Symfony-2.0/src/Symfony",
		'Doctrine\\Common'	=>	"$LIBRARY_PATH/Doctrine-2.0/src/Common/lib/Doctrine/Common",
		'Doctrine\\DBAL'	=>	"$LIBRARY_PATH/Doctrine-2.0/src/DBAL/lib/Doctrine/DBAL",
		'Doctrine\\ORM'		=>	"$LIBRARY_PATH/Doctrine-2.0/src/ORM/lib/Doctrine/ORM",
	),
	'prefixes' => array(
		'Zend_'			=> "$LIBRARY_PATH/ZF2-master/library/Zend",
		'Symbiose_'		=> "$SYMBIOSE_PATH/Symbiose",
		'Twig_'			=> "$LIBRARY_PATH/Twig-trunk/lib/Twig",
		'Swift_'		=> "$LIBRARY_PATH/SwiftMailer-4.1/lib/classes/Swift",
	),
	'fallback_autoloader' => false,
));
// register class loaders
$standardAutoloader->register();
use Zend\Loader\ClassMapAutoloader;
$classMapAutoloader = new ClassMapAutoloader();
$classMapAutoloader->registerAutoloadMap(array(
	'csstidy'		=> "$LIBRARY_PATH/csstidy-1.3/class.csstidy.php",
	'\\csstidy'		=> "$LIBRARY_PATH/csstidy-1.3/class.csstidy.php",
	'JSMin'			=> "$LIBRARY_PATH/jsmin-php/jsmin.php",
	'\\JSMin'		=> "$LIBRARY_PATH/jsmin-php/jsmin.php",
));
$classMapAutoloader->register();
