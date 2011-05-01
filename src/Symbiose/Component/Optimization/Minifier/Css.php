<?php

namespace Symbiose\Component\Optimization\Minifier;

use csstidy;

/**
 * 
 * Use csstidy library to minify css content
 * @author Michael bideau
 *
 */
class Css
{
	static protected $options = array(
		'compress_colors' => true,
		'remove_bslash' => true,
		'compress_font-weight' => true,
		'lowercase_s' => false,
		'optimise_shorthands' => 1,
		'remove_last_;' => false,
		'case_properties' => 1,
		'sort_properties' => false,
		'sort_selectors' => false,
		'merge_selectors' => 2,
		'discard_invalid_properties' => false,
		'css_level' => 'CSS2.1',
		'preserve_css' => false,
		'timestamp' => false
	);
	
	public function minify($content)
	{
		$cssTidy = $this->getCssTidy();
		
		$cssTidy->parse($content);
		
		$cssTidy->load_template('highest_compression');
		
		$content = $cssTidy->print->plain();
		
		/// fix opacity bug
		$content = str_replace('opacity:,', 'opacity:0.', $content);
		
		/*echo '<pre>' . $content . '</pre>'; die();*/
		return $content;
	}
	
	protected function getCssTidy()
	{
		$instance = new csstidy();
		if(!empty(self::$options)) {
			foreach(self::$options as $key => $value) {
				$instance->set_cfg($key, $value);
			}
		}
		return $instance;
	}
}