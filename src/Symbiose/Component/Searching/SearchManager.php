<?php

namespace Symbiose\Component\Searching;

use Symbiose\Component\Searching\Search,
	Symbiose\Component\Searching\Exception\SearchException as Exception,
	Zend\Search\Lucene\Analysis\Analyzer,
	Zend\Search\Lucene\Analysis\Analyzer\Common\TextNum\CaseInsensitive
;

class SearchManager
{
	protected $dir;
	protected $indexes = array(/* 'key' => $index */);
	
	public function __construct($dir, array $indexList = array())
	{
		if(!is_dir($dir)) {
			if(!@mkdir($dir, 0775, true)) {
				throw new Exception("Failed to create directory '$dir'");
			}
		}
		$this->dir = realpath($dir);
		if(!empty($indexList)) {
			foreach($indexList as $key => $indexFile) {
				$this->indexes[$key] = new Search("$dir/$indexFile");
			}
		}
		
		// set default analyzer to Utf8Num
		Analyzer::setDefault(
			//new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive()
			new CaseInsensitive()
		);
	}
	
	public function getIndex($key)
	{
		if(array_key_exists($key, $this->indexes)) {
			return $this->indexes[$key];
		}
		return null;
	}
}