<?php

namespace Symbiose\Component\Caching\Backend;

use Zend\Cache\Backend\File as BaseFile;

class File
	extends BaseFile
{
	/**
     * Make and return a file name (with path)
     *
     * @param  string $id Cache id
     * @return string File name (with path)
     */
    public function getFile($id)
	{
		return parent::_file($id);
	}
}