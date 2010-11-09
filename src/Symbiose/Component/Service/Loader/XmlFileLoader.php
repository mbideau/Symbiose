<?php

namespace Falcon\Site\Component\Service\Loader;

use Symfony\Component\DependencyInjection\Loader\XmlFileLoader as BaseXmlFileLoader;

defined('SERVICE_XML_FILE_LOADER_VALIDATION_FILE')
	|| define('SERVICE_XML_FILE_LOADER_VALIDATION_FILE', LIBRARY_PATH . DS . 'Symfony' . DS . 'src' . DS . 'Symfony' . DS . 'Component' . DS . 'DependencyInjection' . DS . 'Loader' . DS . 'schema' . DS . 'dic' . DS . 'services' . DS . 'services-1.0.xsd');

class XmlFileLoader
	extends BaseXmlFileLoader
{
	/**
     * @throws \RuntimeException         When extension references a non-existent XSD file
     * @throws \InvalidArgumentException When xml doesn't validate its xsd schema
     */
    protected function validateSchema($dom, $file)
    {
        $schemaLocations = array('http://www.symfony-project.org/schema/dic/services' => str_replace('\\', '/', SERVICE_XML_FILE_LOADER_VALIDATION_FILE));

        if ($element = $dom->documentElement->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation')) {
            $items = preg_split('/\s+/', $element);
            for ($i = 0, $nb = count($items); $i < $nb; $i += 2) {
                if (!$this->container->hasExtension($items[$i])) {
                    continue;
                }

                if (($extension = $this->container->getExtension($items[$i])) && false !== $extension->getXsdValidationBasePath()) {
                    $path = str_replace($extension->getNamespace(), str_replace('\\', '/', $extension->getXsdValidationBasePath()).'/', $items[$i + 1]);

                    if (!file_exists($path)) {
                        throw new \RuntimeException(sprintf('Extension "%s" references a non-existent XSD file "%s"', get_class($extension), $path));
                    }

                    $schemaLocations[$items[$i]] = $path;
                }
            }
        }

        $imports = '';
        foreach ($schemaLocations as $namespace => $location) {
            $parts = explode('/', $location);
            $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
            $location = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));

            $imports .= sprintf('  <xsd:import namespace="%s" schemaLocation="%s" />'."\n", $namespace, $location);
        }

        $source = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<xsd:schema xmlns="http://www.symfony-project.org/schema"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    targetNamespace="http://www.symfony-project.org/schema"
    elementFormDefault="qualified">

    <xsd:import namespace="http://www.w3.org/XML/1998/namespace"/>
$imports
</xsd:schema>
EOF
        ;

        $current = libxml_use_internal_errors(true);
        if (!$dom->schemaValidateSource($source)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        libxml_use_internal_errors($current);
    }
}