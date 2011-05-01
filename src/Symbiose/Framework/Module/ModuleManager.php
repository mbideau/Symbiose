<?php

namespace Symbiose\Framework\Module;

use Symbiose\Framework\Module\ModuleManagerInterface,
	Symbiose\Framework\Module\Exception\ModuleException as Exception,
	Symbiose\Framework\Module\Module,
	Symbiose\Component\Service\ServiceContainerAware
;

class ModuleManager
	extends ServiceContainerAware
	implements ModuleManagerInterface
{
	protected $configurationFilename = 'config.xml';
	protected $modulesDirs = array();
	protected $modules = array();
	protected $registeredModules = array();
	protected $bootstrapList = array();
	protected $logger;
	
	public function __construct($logger = null)
	{
		$this->logger = $logger;
	}
	
	public function getModules()
	{
		return $this->modules;
	}
	
	public function getRegisteredModules()
	{
		return $this->registeredModules;
	}
	
	public function getProperModuleName($module)
	{
		return ucfirst(preg_replace_callback(
			'|[-_.](\S)|',
			create_function(
				'$matches',
				'return ucfirst($matches[1]);'
			),
			$module
		));
	}
	
	public function getControllerPath($module, $controllerFilename)
	{
		// get proper module name
		$moduleName = $this->getProperModuleName($module);
		// if this module doesn't exist
		if(!$this->has($moduleName)) {
			throw new Exception(__FUNCTION__ . " : module '$moduleName' doesn't exists");
		}
		return $this->get($moduleName)->getControllerPath($controllerFilename);
	}
	
	public function get($moduleName)
	{
		$module = $this->getProperModuleName($moduleName);
		if(empty($module)) {
			throw new Exception(__FUNCTION__ . " : empty module name");
		}
		return array_key_exists($module, $this->modules) ? $this->modules[$module] : null;
	}
	
	protected function has($moduleName)
	{
		$module = $this->getProperModuleName($moduleName);
		if(empty($module)) {
			throw new Exception(__FUNCTION__ . " : empty module name");
		}
		return array_key_exists($module, $this->modules);
	}
	
	public function __sleep()
	{
		return array(
			'modulesDirs',
			'configurationFilename',
			'modules',
			'bootstrapList'
		);
	}
	/*
	public function updateModulesServiceContainer()
	{
		if(is_array($this->modules)) {
			foreach($this->modules as $module) {
				$module->setServiceContainer($this->serviceContainer);
			}
		}
		return $this;
	}
	*/
	public function setModulesDirs(array $modulesDirs)
	{
		$this->modulesDirs = $modulesDirs;
		return $this;
	}
	
	public function registerModulesDirs(array $modulesDirs)
	{
		$this->modulesDirs = array_merge($this->modulesDirs , $modulesDirs);
		return $this;
	}
	
	public function registerModules(array $modulesPath)
	{
		$this->registeredModules = array_merge($this->registeredModules, $modulesPath);
		return $this;
	}
	
	public function addModules(array $modulesPath)
	{
		foreach($modulesPath as $path) {
			$this->addModule($path);
		}
		return $this;
	}
	
	public function setBootstrapList(array $list)
	{
		$this->bootstrapList = $list;
		return $this;
	}
	
	public function loadModules()
	{
		// if the bootstrap list is not already defined
		if(empty($this->bootstrapList)) {
			! $this->logger ?: $this->logger->debug('ModuleManager: bootstrap list not available');
			// add modules from modules dirs
			! $this->logger ?: $this->logger->debug('ModuleManager: loading registered modules dirs ...');
			foreach($this->modulesDirs as $dir) {
				! $this->logger ?: $this->logger->debug('ModuleManager: loading module dir : ' . $dir . ' ...');
				foreach(new \DirectoryIterator($dir) as $fileInfo) {
					if(!$fileInfo->isDot() || strpos($fileInfo->getFilename(), '.') !== 0) {
						$modulePath = $fileInfo->getRealPath();
						! $this->logger ?: $this->logger->debug('ModuleManager: adding module : ' . $modulePath . ' ...');
				    	$this->addModule($modulePath);
					}
				}
			}
			
			// add registered modules
			! $this->logger ?: $this->logger->debug('ModuleManager: loading registered modules ...');
			if(!empty($this->registeredModules)) {
				foreach($this->registeredModules as $path) {
					! $this->logger ?: $this->logger->debug('ModuleManager: adding module : ' . $path . ' ...');
					$this->addModule($path);
				}
			}
			
			// check dependencies
			! $this->logger ?: $this->logger->debug('ModuleManager: checking dependencies ...');
			foreach($this->modules as $module) {
				$this->checkDependencies($module);
			}
		
			// get the bootstrap list
			! $this->logger ?: $this->logger->debug('ModuleManager: building bootstrap list ...');
			$this->bootstrapList = $this->buildBootstrapList();
			! $this->logger ?: $this->logger->debug('ModuleManager: bootstrap list is ' . var_export($this->bootstrapList, true));
		}
		
		// bootstrap modules (if bootstrapt list is not empty)
		if(!empty($this->bootstrapList)) {
			! $this->logger ?: $this->logger->debug('ModuleManager: bootstraping ...');
			foreach($this->bootstrapList as $moduleName) {
				! $this->logger ?: $this->logger->debug('ModuleManager: bootstraping module ' . $moduleName . ' ...');
				// get module
				$module = $this->modules[$moduleName];
				// bootstrap it
				$module->bootstrap();
			}
		}
	}
	
	protected function buildBootstrapList()
	{
		// build the boostrap list from the modules list
		$bootstrapList = array_keys($this->modules);
		! $this->logger ?: $this->logger->debug('ModuleManager: initial bootstrap list is <pre>' . var_export($bootstrapList, true) . '</pre>');
		// for each module
		foreach($this->modules as $moduleName => $module) {
			// get module order
			$order = $module->getOrder();
			! $this->logger ?: $this->logger->debug('ModuleManager: placing module ' . $moduleName . ' with order : ' . var_export($order, true));
			// if an order is specified
			if(!empty($order)) {
				// foreach module position
				foreach($order as $refName => $refPosition) {
					$refName = $this->getProperModuleName($refName);
					// if the module is added
					if(array_key_exists($refName, $this->modules)) {
						// get the module
						$refModule = $this->modules[$refName];
						// get the module order
						$refModuleOrder = $refModule->getOrder();
						// if the current module is specified
						if(array_key_exists($moduleName, $refModuleOrder)) {
							// if the position is in conflict
							if($refPosition == $refModuleOrder[$moduleName]) {
								throw new Exception("function buildBootstrapList : position '$position' of the module '$moduleName' is in conflict wth the module '$refName' order");
							}
						}
						// get the module position
						$moduleIndex = array_search($moduleName, $bootstrapList);
						// get the ref module position
						$refIndex = array_search($refName, $bootstrapList);
						// remove the module
						switch($refPosition) {
							case 'before':
								if($moduleIndex > $refIndex) {
									! $this->logger ?: $this->logger->debug('ModuleManager: placing module ' . $moduleName . '[' . $moduleIndex . '] before module ' . $refName  . '[' . $refIndex . ']');
									// remove the module
									array_splice($bootstrapList, $moduleIndex, 1);
									// put the module just before the refered one
									array_splice($bootstrapList, $refIndex, 1, array($moduleName, $refName));
								}
								break;
							case 'after':
								if($moduleIndex < $refIndex) {
									! $this->logger ?: $this->logger->debug('ModuleManager: placing module ' . $moduleName . '[' . $moduleIndex . '] after module ' . $refName  . '[' . $refIndex . ']');
									// remove the module
									array_splice($bootstrapList, $moduleIndex, 1);
									// put the module just after the refered one
									array_splice($bootstrapList, $refIndex, 0, $moduleName);
								}
								break;
							default:
								throw new Exception("function buildBootstrapList : position '$refPosition' is invalid for order of the module '$moduleName'");
								break;
						}
					}
					else {
						throw new Exception("function buildBootstrapList : module $refName is missing, and module $moduleName refers to it");
					}
				}
			}
		}
		return $bootstrapList;
	}
	
	public function addModule($modulePath)
	{
		// if module path is empty
		if(empty($modulePath)) {
			throw new Exception("function addModule : module path is empty");
		}
		// if module dir doesn't exist
		//if(!is_dir($modulePath)) {
		//	throw new Exception("function addModule : module directory '$modulePath' doesn't exist");
		//}
		// get the module name
		$moduleName = $this->getModuleName($modulePath);
		// if the module is already added
		if(isset($moduleName, $this->modules[$moduleName])) {
			throw new Exception("function addModule : module '$moduleName' is already added");
		}
		/*
		// if the module is already pending to be added
		if(in_array($moduleName, $this->pendingModuleToBeLoaded)) {
			throw new Exception("function addModule : detecting dependencies loop for module '$moduleName'");
		}
		// add the module to the pending module to be added
		$this->pendingModuleToBeLoaded[] = $moduleName;
		*/
		// build the configuration file
		$configurationFilePath = $modulePath . '/' . $this->configurationFilename;
		// get a module instance
		//$module = new Module($moduleName, $modulePath);
		$moduleClassname = 'Modules\\' . $moduleName;
		include $modulePath . '/module.php';
		$module = new $moduleClassname;
		// get a module builder
		//$moduleBuilder = new ModuleBuilder($module, $this->logger);
		// build the module from the configuration file
		//$moduleBuilder->build($configurationFilePath);
		// set it the service container
		$module->setServiceContainer($this->serviceContainer);
		// add it
		$this->modules[$moduleName] = $module;
		/*
		// load the module dependencies
		$this->loadDependencies($module);
		// remove the module to the pending module to be loaded
		$this->pendingModuleToBeLoaded = array_diff($this->pendingModuleToBeLoaded, array($moduleName));
		*/
		return $this;
	}
	
	protected function getModuleName($modulePath)
	{
		return $this->getProperModuleName(basename($modulePath));
	}
	
	protected function checkDependencies(Module $module)
	{
		// if module is empty
		if(empty($module)) {
			throw new Exception("function checkDependencies : module is empty");
		}
		// get module dependencies
		$dependencies = $module->getDependencies();
		// if dependencies are not empties
		if(!empty($dependencies)) {
			// foreach dependency module
			foreach($dependencies as $moduleName => $moduleVersionPattern) {
				// if the module is not already added
				if(!$this->has($moduleName)) {
					throw new Exception("function checkDependencies : module '" . $module->getName() . "' depends on module '$moduleName' that is not added");
				}
				// if the version pattern is not empty and not equals to '*'
				if(!empty($moduleVersionPattern) && $moduleVersionPattern != '*') {
					// if the version pattern is not valid
					if(!preg_match('#^(>|<|<=|>=|=|!=)\s+(\S+)$#', $moduleVersionPattern, $matches)) {
						throw new Exception("function checkDependencies : version pattern '$moduleVersionPattern' is not valid");
					}
					// get the version operator
					$versionOperator = $matches[1];
					// get the version
					$version = $matches[2];
					// get the module version
					$moduleVersion = $this->get($moduleName)->getVersion();
					// check the version according to operator
					if(!$this->isVersionValid($moduleVersion, $versionOperator, $version)) {
						throw new Exception("function checkDependencies : module '" . $module->getName() . "' depends on module '$moduleName' version '$versionOperator $version' that is in different version '$moduleVersion'");
					}
				}
			}
		}
	}
	
	protected function getConfigurationFromFile($filepath)
	{
		$configuration = null;
		// if the configuration file doesn't exist
		if(!file_exists($filepath)) {
			throw new Exception("function getConfigurationFromFile : configuration file '$filepath' doesn't exist");
		}
		// get a file object
		$configurationFileObject = new \Symfony\Component\HttpFoundation\File\File($filepath);
		// get extension of file
		$configurationFileExtension = $configurationFileObject->getDefaultExtension();
		// if the extension is empty
		if(empty($configurationFileExtension)) {
			throw new Exception("function getConfigurationFromFile : failed to get extension of configuration file '$filepath'");
		}
		// according to extension
		switch($configurationFileExtension) {
			case '.ini':
				$configuration = new \Zend\Config\Ini($filepath);
				break;
			case '.xml':
				$configuration = new \Zend\Config\Xml($filepath);
				break;
			// extension is not ini or xml
			default:
				throw new Exception("function getConfigurationFromFile : extension '$configurationFileExtension' of configuration file '$filepath' is not supported");
				break;
		}
		return $configuration;
	}
	
	protected function isVersionValid($moduleVersion, $operator, $versionRequired)
	{
		switch($operator) {
			case '=':
				if($moduleVersion == $versionRequired) {
					return true;
				}
				break;
			case '>':
				if($moduleVersion > $versionRequired) {
					return true;
				}
				break;
			case '<':
				if($moduleVersion < $versionRequired) {
					return true;
				}
				break;
			case '>=':
				if($moduleVersion >= $versionRequired) {
					return true;
				}
				break;
			case '<=':
				if($moduleVersion <= $versionRequired) {
					return true;
				}
				break;
			case '!=':
				if($moduleVersion != $versionRequired) {
					return true;
				}
				break;
		}
		return false;
	}
}