<?php

/** @namespace */
namespace Symbiose\Component\File;

// import SPL classes/interfaces into local scope
use DirectoryIterator,
    FilterIterator,
    RecursiveIterator,
    RecursiveDirectoryIterator,
    RecursiveIteratorIterator;

/**
 * Locate files containing PHP classes, interfaces, or abstracts
 * 
 * @package    Symbiose Component File
 */
class ExtensionFileLocater extends FilterIterator
{
    protected $extensions = array();
    
    /**
     * Create an instance of the locater iterator
     * 
     * Expects either a directory, or a DirectoryIterator (or its recursive variant) 
     * instance.
     * 
     * @param  string|DirectoryIterator $dirOrIterator 
     * @return void
     */
    public function __construct($dirOrIterator = '.', $extensions = array())
    {
        if (is_string($dirOrIterator)) {
            if (!is_dir($dirOrIterator)) {
                throw new Exception\InvalidArgumentException('Expected a valid directory name');
            }

            $dirOrIterator = new RecursiveDirectoryIterator($dirOrIterator);
        }
        if (!$dirOrIterator instanceof DirectoryIterator) {
            throw new Exception\InvalidArgumentException('Expected a DirectoryIterator');
        }

        if ($dirOrIterator instanceof RecursiveIterator) {
            $iterator = new RecursiveIteratorIterator($dirOrIterator);
        } else {
            $iterator = $dirOrIterator;
        }

        if(empty($extensions)) {
        	throw new Exception\InvalidArgumentException('Expected not empty extensions');
        }
        
        $this->extensions = $extensions;
        
        parent::__construct($iterator);
        $this->rewind();
    }

    /**
     * Filter for files containing PHP classes, interfaces, or abstracts
     * 
     * @return bool
     */
    public function accept()
    {
        $file = $this->getInnerIterator()->current();

        // If we somehow have something other than an SplFileInfo object, just 
        // return false
        if (!$file instanceof \SplFileInfo) {
            return false;
        }

        // If we have a directory, it's not a file, so return false
        if (!$file->isFile()) {
            return false;
        }

        // If valid extension keep
        foreach ($this->extensions as $ext) {
        	if ($file->getBasename($ext) != $file->getBasename()) {
        		return true;
        	}
        }
        
        return false;
    }
}
