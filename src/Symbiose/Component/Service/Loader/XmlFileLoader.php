<?php

namespace Symbiose\Component\Service\Loader;

use Symfony\Component\DependencyInjection\Loader\XmlFileLoader as BaseXmlFileLoader;

class XmlFileLoader
	extends BaseXmlFileLoader
{
	protected $serviceSchemaBasePath;
	protected $serviceSchemaFilename = 'services-1.0.xsd';
	
	public function setServiceSchemaBasePath($path)
	{
		$this->serviceSchemaBasePath = $path;
	}
	
	protected function getServiceSchemaPath()
	{
		return $this->serviceSchemaBasePath . '/' . $this->serviceSchemaFilename;
	}
	
	/**
     * @throws \RuntimeException         When extension references a non-existent XSD file
     * @throws \InvalidArgumentException When xml doesn't validate its xsd schema
     */
    protected function validateSchema($dom, $file)
    {
        $schemaLocations = array('http://www.symfony-project.org/schema/dic/services' => $this->getServiceSchemaPath());

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