<?php

namespace Falcon\Site\Component\Routing\Loader;

use Symfony\Component\Routing\Loader\XmlFileLoader as BaseXmlFileLoader;

defined('ROUTER_XML_FILE_LOADER_VALIDATION_FILE')
	|| define('ROUTER_XML_FILE_LOADER_VALIDATION_FILE', LIBRARY_PATH . DS . 'Symfony' . DS . 'src' . DS . 'Symfony' . DS . 'Component' . DS . 'Routing' . DS . 'Loader' . DS . 'schema' . DS . 'routing' . DS . 'routing-1.0.xsd');

class XmlFileLoader
	extends BaseXmlFileLoader
{
	/**
     * @throws \InvalidArgumentException When xml doesn't validate its xsd schema
     */
    protected function validate($dom, $file)
    {
        $parts = explode('/', str_replace(DS, '/', ROUTER_XML_FILE_LOADER_VALIDATION_FILE));
        $drive = '\\' === DS ? array_shift($parts).'/' : '';
        $location = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));

        $current = libxml_use_internal_errors(true);
        if (!$dom->schemaValidate($location)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        libxml_use_internal_errors($current);
    }
}