<?php

namespace Symbiose\Component\Service;

use Symbiose\Component\Service\ServiceContainerInterface,
	Symfony\Component\DependencyInjection\ContainerBuilder,
	Symbiose\Component\Service\ServiceMergeableInterface,
	Symbiose\Component\Service\Exception\ServiceException as Exception,
	Symfony\Component\HttpFoundation\File\File as File,
	Symbiose\Component\Service\Loader\XmlFileLoader as ServiceContainerLoader
;

class ServiceContainer
	extends ContainerBuilder
	implements ServiceContainerInterface
{
	public function merge(ContainerBuilder $container)
    {
        /*var_dump(array(
        	'current' => array(
        		$this->getDefinitions(),
        		$this->getAliases(),
        		$this->getResources(),
        		$this->getExtensionConfigs()
        	),
        	'new' => array(
        		$container->getDefinitions(),
        		$container->getAliases(),
        		$container->getResources(),
        		$container->getExtensionConfigs()
        	)
        ));*/
    	// merge serivce container as usual
    	parent::merge($container);
    	// merge services actually runing
    	$ids = $this->getServiceIds();
    	foreach($ids as $id) {
    		// if the new container contains a service already loaded
    		if($container->has($id) && $id != 'service_container') {
    			// just remove the loaded service
    			// so it will be automatically reloaded (the next 'get' call)
    			$this->remove($id);
    		}
    	}
    }
    
	/**
     * Removes a service.
     *
     * @param string $id      The service identifier
     */
    public function remove($id)
    {
        $this->services[$id] = null;
        unset($this->services[$id]);
    }
    
    static public function getServiceContainerFromFile($filepath)
	{
		$sc = null;
		// get the realpath
		$filepathReal = realpath($filepath);
		if(!$filepathReal) {
			throw new Exception(__FUNCTION__ . " : service file '$filepath' doesn't exist");
		}
		$filepath = $filepathReal;
		// if the file doesn't exist
		if(!file_exists($filepath)) {
			throw new Exception(__FUNCTION__ . " : service file '$filepath' doesn't exist");
		}
		// get a file object
		$scFileObject = new File($filepath);
		// get extension of file
		$scFileExtension = $scFileObject->getDefaultExtension();
		// if the extension is empty
		if(empty($scFileExtension)) {
			throw new Exception(__FUNCTION__ . " : failed to get extension of service file '$filepath'");
		}
		// according to extension
		switch($scFileExtension) {
			case '.xml':
				// new service container
				$sc = new ServiceContainer();
				// service container loader
				$scl = new ServiceContainerLoader($sc);
				// set the path to service schema
				$scl->setServiceSchemaBasePath(dirname($filepath));
				// load the service container from the file
				$scl->load($filepath);
				break;
			// extension is not xml
			default:
				throw new Exception(__FUNCTION__ . " : extension '$serviceFileExtension' of service file '$filepath' is not supported");
				break;
		}
		return $sc;
	}
}