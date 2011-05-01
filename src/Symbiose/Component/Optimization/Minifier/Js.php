<?php

namespace Symbiose\Component\Optimization\Minifier;

use JSMin;

/**
 * 
 * Use 
 * @author Michael bideau
 *
 */
class Js
{
	static protected $options = array(
		
	);
	
	public function minify($content)
	{
		if(!class_exists('JSMin')) {
			require_once('jsmin.php');
		}
		
		$minified = JSMin::minify($content);
		
		return $minified;
	}
}