<?php

namespace Symbiose\Component\Searching;

use Zend\Search\Lucene\Lucene,
	Symbiose\Component\Searching\Exception\SearchException as Exception
;

class Search
{
	protected $file;
	protected $index;
	
	public function __construct($file)
	{
		$this->file = $file;
		if(!file_exists($file)) {
			$this->index = Lucene::create($file);
		}
		else {
			$this->index = Lucene::open($file);
		}
	}
	
	public function __call($name, $arguments) {
        if($arguments) {
			$argNumber = count($arguments);
			switch($argNumber) {
				case 0:
					return $this->index->$name();
				break;
				case 1:
					return $this->index->$name($arguments[0]);
				break;
				case 2:
					return $this->index->$name($arguments[0], $arguments[1]);
				break;
			}
        }
        else {
        	return $this->index->$name();
        }
    }
}