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

return array(
	'libs' => array(
		// namespaces
		'Application'		=> 	$LIBRARY_PATH,
		'Zend'				=>	"$LIBRARY_PATH/ZF2-master/library",
		'Symbiose'			=>	$SYMBIOSE_PATH,
		'Symfony'			=>	"$LIBRARY_PATH/Symfony-2.0/src",
		'Doctrine\\Common'	=>	"$LIBRARY_PATH/Doctrine-2.0/src/Common/lib",
		'Doctrine\\DBAL'	=>	"$LIBRARY_PATH/Doctrine-2.0/src/DBAL/lib",
		'Doctrine\\ORM'		=>	"$LIBRARY_PATH/Doctrine-2.0/src/ORM/lib",
		//prefixes
		'Zend_'				=> 	"$LIBRARY_PATH/ZF2-master/library",
		'Symbiose_'			=> 	$SYMBIOSE_PATH,
		'Twig_'				=> 	"$LIBRARY_PATH/Twig-trunk/lib",
		'Swift_'			=> 	"$LIBRARY_PATH/SwiftMailer-4.1/lib/classes"
	),
	'files' => array(
		'csstidy'			=> "$LIBRARY_PATH/csstidy-1.3/class.csstidy.php",
		'csstidy_print'		=> "$LIBRARY_PATH/csstidy-1.3/class.csstidy_print.php",
		'csstidy_optimise'	=> "$LIBRARY_PATH/csstidy-1.3/class.csstidy_optimise.php",
		'JSMin'				=> "$LIBRARY_PATH/jsmin-php/jsmin.php"
	)
);
?>