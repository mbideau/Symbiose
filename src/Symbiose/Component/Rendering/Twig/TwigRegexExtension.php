<?php
/*
namespace Symbiose\Component\Rendering\Twig;

use Twig_Extension,
	Twig_TokenParser,
	Twig_Token,
	Twig_Filter_Function
;
*/
class Symbiose_Component_Rendering_Twig_TwigRegexExtension extends Twig_Extension
{
	/**
	 * Name of this extension
	 *
	 * @return string
	 */
    public function getName()
    {
        return 'Regex';
    }
	
	/**
	 * Returns a list of filters.
	 *
	 * @return array
	 */
    public function getFilters()
    {
        return array(
            'safename' => new Twig_Filter_Function(
            	'twig_safename_filter'
        	),
        );
    }
    
    
}

function twig_safename_filter($input)
{
    return preg_replace(
    	'#[_-]$#',
    	'',
	    preg_replace(
	    	'#[^a-zA-Z0-9_-]#',
	    	'',
	    	str_replace(
	    		' ',
	    		'-',
	    		normalize(
	    			lc_latin1(
	    				strip_tags(
	    					$input
	    				)
	    			)
	    		)
	    	)
	    )
    );
}

define("LATIN1_UC_CHARS", "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝ");
define("LATIN1_LC_CHARS", "àáâãäåæçèéêëìíîïðñòóôõöøùúûüý");
function uc_latin1($str) {
    $str = strtoupper(strtr($str, LATIN1_LC_CHARS, LATIN1_UC_CHARS));
    return strtr($str, array("ß" => "SS"));
}
function lc_latin1($str) {
    $str = strtolower(strtr($str, LATIN1_UC_CHARS, LATIN1_LC_CHARS));
    return strtr($str, array("SS" => "ß"));
}

function normalize($string) {
    $table = array(
        'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
        'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
        'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
        'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
    );  
    return strtr($string, $table);
}