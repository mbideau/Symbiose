<?php

//namespace Falcon\Site\Component\Caching\Backend;

class Falcon_Site_Component_Caching_Backend_File
	extends Zend_Cache_Backend_File
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