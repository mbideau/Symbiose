<?php

namespace Falcon\Site\Framework\Module;

use Exception\ModuleException,
	Falcon\Site\Framework\Module\Module,
	Symfony\Component\HttpFoundation\File\File
;

class ModuleBuilder
{
	protected $module;
	
	public function __construct(Module $module)
	{
		$this->module = $module;
	}
	
	public function build($filepath)
	{
		// if the file is not a string
		if(!is_string($filepath)) {
			throw new Exception("function __construct : parameter 'filepath' must be a string");
		}
		// if the file doesn't exist
		if(!file_exists($filepath)) {
			throw new Exception("function __construct : file '$filepath' doesn't exist");
		}
		
		// get a file object
		$fileObject = new File($filepath);
		// get extension of file
		$fileExtension = $fileObject->getDefaultExtension();
		// if the extension is empty
		if(empty($fileExtension)) {
			throw new Exception("function __construct : failed to get extension of configuration file '$filepath'");
		}
		// according to extension
		switch($fileExtension) {
			case '.ini':
				$configuration = new \Zend_Config_Ini($filepath);
				break;
			case '.xml':
				$configuration = new \Zend_Config_Xml($filepath);
				// if the configuration is not empty
				if(!empty($configuration)) {
					// version
					$version = trim($configuration->get('version', null));
					// dependencies
					$dependencies = $configuration->get('dependencies', array());
					$validDependencies = array();
					if(!empty($dependencies)) {
						foreach($dependencies as $module) {
							$moduleName = trim(ucfirst($module->get('name', '')));
							$moduleVersion = trim($module->get('version', ''));
							if(!empty($moduleName) && !empty($moduleVersion)) {
								$validDependencies[$moduleName] = $moduleVersion;
							}
						}
					}
					$dependencies = $validDependencies;
					// order
					$order = $configuration->get('order', array());
					$validOrder = array();
					if(!empty($order)) {
						foreach($order as $module) {
							$moduleName = trim(ucfirst($module->get('name', '')));
							$modulePosition = trim(strtolower($module->get('position', '')));
							if(!empty($moduleName) && !empty($modulePosition)) {
								$validOrder[$moduleName] = $modulePosition;
							}
						}
					}
					$order = $validOrder;
					// services
					$servicesFile = $this->convertToAbsolutePath($configuration->get('services-file', null), $this->module->getPath());
					// cache
					$cacheFile = $this->convertToAbsolutePath($configuration->get('cache-file', null), $this->module->getPath());
					// route
					$routeFile = $this->convertToAbsolutePath($configuration->get('route-file', null), $this->module->getPath());
					// register classes
					$registerClasses = $configuration->get('register-classes', array());
					$validRegisterClasses = array(
						'namespaces' => array(),
						'prefixes' => array()
					);
					if(!empty($registerClasses)) {
						foreach($registerClasses as $type => $list) {
							$listArray = $list->toArray();
							if(array_key_exists('name', $listArray) && array_key_exists('path', $listArray) && count($listArray) == 2) {
								list($namespaceName, $namespacePath) = array_values($listArray);
								$namespaceName = trim(ucfirst($namespaceName));
								$namespacePath = $this->convertToAbsolutePath(trim(strtolower($namespacePath)), $this->module->getPath());
								if($type == 'prefix') {
									$validRegisterClasses['prefixes'][$namespaceName] = $namespacePath;
								}
								else {
									$validRegisterClasses['namespaces'][$namespaceName] = $namespacePath;
								}
							}
							else {
								foreach($list as $index => $namespace) {
									$namespaceName = '';
									$namespacePath = '';
									if($namespace instanceof \Zend_Config) {
										$namespaceName = $namespace->get('name', '');
										$namespacePath = $namespace->get('path', '');
									}
									elseif(is_array($namespace)) {
										$namespaceName = array_key_exists('name', $namespace) ? $namespace['name'] : '';
										$namespacePath = array_key_exists('path', $namespace) ? $namespace['path'] : '';
									}
									$namespaceName = trim(ucfirst($namespaceName));
									$namespacePath = $this->convertToAbsolutePath(trim(strtolower($namespacePath)), $this->module->getPath());
									if(!empty($namespaceName) && !empty($namespacePath)) {
										if($type == 'prefix') {
											$validRegisterClasses['prefixes'][$namespaceName] = $namespacePath;
										}
										else {
											$validRegisterClasses['namespaces'][$namespaceName] = $namespacePath;
										}
									}
								}
							}
						}
					}
					$registerClasses = $validRegisterClasses;
					// controllers dir
					$controllersDir = $this->convertToAbsolutePath($configuration->get('controllers-dir', null), $this->module->getPath());
					// update the module
					$this->module
						->setVersion($version)
						->setDependencies($dependencies)
						->setOrder($order)
						->setRegisterClasses($registerClasses)
						->setControllersDir($controllersDir)
						->setServicesFile($servicesFile)
						->setCacheFile($cacheFile)
						->setRouteFile($routeFile)
					;
				}
				break;
			// extension is not ini or xml
			default:
				throw new Exception("function __construct : extension '$fileExtension' of configuration file '$filepath' is not supported");
				break;
		}
	}
	
	protected function convertToAbsolutePath($path, $absPathBase)
	{
		// if the file is not empty
		if(!empty($path)) {
			// if the path is not an absolute path
			if(strpos($path, '/') !== 0) {
				// it to an absolute path
				return $absPathBase . DS. $path;
			}
		}
		return $path;
	}
}