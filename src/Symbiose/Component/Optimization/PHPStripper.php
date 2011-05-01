<?php

namespace Symbiose\Component\Optimization;

class PHPStripper
{
	/**
	 * Removes comments from a PHP source string.
	 *
	 * We don't use the PHP php_strip_whitespace() function
	 * as we want the content to be readable and well-formatted.
	 *
	 * @param string $source A PHP string
	 *
	 * @return string The PHP string with the comments removed
	 */
	static public function stripComments($source)
	{
	    if (!function_exists('token_get_all')) {
	        return $source;
	    }
		
	    $output = '';
	    foreach (token_get_all($source) as $token) {
	        if (is_string($token)) {
	            $output .= $token;
	        }
	        elseif (!in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
	            $output .= $token[1];
	        }
	    }
	
	    // replace multiple new lines with a single newline
	    $output = preg_replace(array('/\s+$/Sm', '/\n+/S'), "\n", $output);
	
	    // reformat {} "a la python"
	    $output = preg_replace(array('/\n\s*\{/', '/\n\s*\}/'), array(' {', ' }'), $output);
	
	    return $output;
	}
}