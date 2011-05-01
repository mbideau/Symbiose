<?php

namespace Symbiose\Component\Routing\Loader;

use Symfony\Component\Routing\Loader\XmlFileLoader as BaseXmlFileLoader;

class XmlFileLoader
	extends BaseXmlFileLoader
{
	protected $routingSchemaBasePath;
	protected $routingSchemaFilename = 'routing-1.0.xsd';
	
	public function setRoutingSchemaBasePath($path)
	{
		$this->routingSchemaBasePath = $path;
	}
	
	protected function getRoutingSchemaPath()
	{
		return $this->routingSchemaBasePath . '/' . $this->routingSchemaFilename;
	}
	
	/**
     * @throws \InvalidArgumentException When xml doesn't validate its xsd schema
     */
    protected function validate(\DOMDocument $dom, $file)
    {
        $parts = explode('/', $this->getRoutingSchemaPath());
        $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
        $file = $drive.implode('/', array_map('rawurlencode', $parts));
        if(strpos($file, '../') !== false || strpos($file, './') !== false) {
        	$file = realpath($file);
        }
        $location = 'file://'.$file;
        $current = libxml_use_internal_errors(true);
        if (!$dom->schemaValidate($location)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        libxml_use_internal_errors($current);
    }
}