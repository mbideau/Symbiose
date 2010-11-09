<?php

namespace Falcon\Site\Component;

class Debug
{
	public static function dump($value, $exit = false, $useZend = false)
	{
		if($useZend) {
			require_once 'Zend/Debug.php';
			\Zend_Debug::dump($value);
		}
		else {
			echo "<pre>DEBUG: \n";
			var_export($value);
			echo '</pre>';
		}
		if($exit) {
			exit;
		}
	}
}