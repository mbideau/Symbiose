<?php

namespace Falcon\Site\Framework;

use Falcon\Site\Component\Logging\FileLogger;

use Falcon\Site\Component\ClassLoader\ClassLoaderStateful as ClassLoader,
	Falcon\Site\Component\Caching\CacheManagerInterface as CacheManager,
	Falcon\Site\Component\Service\ServiceContainerStateful as ServiceContainer,
	Falcon\Site\Framework\Module\ModuleManagerStateful as ModuleManager,
	Falcon\Site\Framework\Exception\ApplicationException as Exception,
	Falcon\Site\Component\Object\StatefulInterface,
	Falcon\Site\Component\Service\ServiceStarter,
	Symfony\Component\HttpFoundation\Request
;

class Application
	implements StatefulInterface
{
	protected $bootstraped = false;
	protected $classLoader;
	protected $cacheManager;
	protected $modules = array();
	protected $modulesDirs = array();
	protected $moduleManager;
	protected $serviceContainer;
	protected $debug = false;
	protected $request;
	protected $serviceStarter;
	protected $state;
	
	public function __construct(ClassLoader $classLoader, CacheManager $cacheManager, $debug = false)
	{
		FileLogger::setDefaultLogDir(LOG_PATH);
		FileLogger::setDefaultLogFilename('default.log');
		FileLogger::directLog('Application starting ...', FileLogger::DEBUG, false);
		$this->classLoader = $classLoader;
		$this->cacheManager = $cacheManager;
		$this->debug = $debug;
	}
	
	public function getState()
	{
		return serialize(array(
			'debug' => $this->debug,
			'modules' => $this->modules,
			'modulesDirs' => $this->modulesDirs,
			'bootstraped' => $this->bootstraped
		));
	}
	
	public function updateState()
	{
		$this->state = $this->getState();
	}
	
	public function setState($state)
	{
		$this->state = $state;
	}
	
	public function restoreState(array $parameters = array())
	{
		
		FileLogger::directLog('Restoring state ...');
		//-- use a starter
		$starter = new ServiceStarter($this->serviceContainer);
		$starter->restoreState();
		// if there are services to restore
		$services = $starter->getServices();
		if(!empty($services)) {
			FileLogger::directLog('Starter : there are services to restore');
			if($starter->restoreServices(array(
				'cache_manager',
				'class_loader',
				'service_container',
			))) {
				$this->serviceContainer = $starter->getServiceContainer()->get('service_container');
				
				// load kernel listeners
				$this->loadKernelListeners();
				
				$this->bootstraped = true;
				
				// update the state
				$this->updateState();
			}
		}
		// no service to restore
		else {
			FileLogger::directLog('Starter : no service to restore');
			// add dynamic services to starter
			$starter
				->addService('class_loader', 'saveState', 'restoreState')
				->addService('cache_manager', 'saveState', 'restoreState')
				->addService('service_container', 'saveState', 'restoreState')
				->addService('module_manager', 'saveState', 'restoreState')
			;
		}
		// add the starter as a service available
		$this->serviceStarter = $starter;
		$this->serviceContainer->set('service_starter', $this->serviceStarter);
		
		return $this;
	}
	
	public function saveState(array $parameters = array())
	{
		if(!$this->debug) {
			// get the current state
			$state = $this->getState();
			// if we need to save it (state is different)
			if($this->state != $state) {
				FileLogger::directLog('Saving state ...');
				//-- use a starter
				if(!$this->serviceStarter) {
					if(!$this->serviceContainer->has('service_starter')) {
						FileLogger::directLog('Failed to save application state (no services starter available)');
						throw new Exception("Failed to save application state (no services starter available)");
					}
					$this->serviceStarter = $this->serviceContainer->get('service_starter');
				}
				// service container must have the cache manager
				if(!$this->serviceContainer->has('cache_manager')) {
					if(!$this->cacheManager) {
						FileLogger::directLog('Failed to save application state (no cache manager available)');
						throw new Exception("Failed to save application state (no cache manager available)");
					}
					$this->serviceContainer->set('cache_manager', $this->cacheManager);
				}
				// if there are services to save
				$services = $this->serviceStarter->getServices();
				FileLogger::directLog(count($services) . ' services to save ...');
				if(!empty($services)) {
					try {
						$this->serviceStarter->saveServices(array(
							'class_loader',
							'cache_manager',
							'service_container'
						));
						$this->serviceStarter->saveState();
					}
					catch(\Exception $e) {
						echo "<pre>" . print_r(array(
							'message' => $e->getMessage(),
							'trace' => $e->getTraceAsString()
						), true) . "</pre>";
					}
				}
				FileLogger::directLog('State saved successfully (' . count($services) . ' services)');
			}
		}
		return $this;
	}
	
	public function __destruct()
	{
		FileLogger::directLog('Application ending ...');
		try {
			$this->saveState();
		}
		catch(\Exception $exception) {
			error_log(sprintf(
				"Application::%s : Uncaught PHP Exception %s: '%s' at %s line %s\nTrace: %s",
				__FUNCTION__,
				get_class($exception),
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine(),
				$exception->getTraceAsString()
			));
		}
	}
	
	protected function setClassLoaderAsAService()
	{
		// set it as a service available
		$this->serviceContainer->set('class_loader', $this->classLoader);
		return $this;
	}
	
	protected function initServiceContainer()
	{
		// no service container exists => create a new one
		if(empty($this->serviceContainer)) {
			$this->serviceContainer = new ServiceContainer();
		}
		return $this;
	}
	
	protected function setCacheManagerAsAService()
	{
		// set the cache manager as a service available
		$this->serviceContainer->set('cache_manager', $this->cacheManager);
	}
	
	protected function setModuleManagerAsAService()
	{
		// define it as a service
		$this->serviceContainer->set('module_manager', $this->moduleManager);
	}
	
	protected function initModuleManager()
	{
		// no module manager exists => create one
		if(!$this->moduleManager) {
			// create it
			$this->moduleManager = new ModuleManager();
			$this->moduleManager
				->setServiceContainer($this->serviceContainer)
				->registerModules($this->modules)
				->registerModulesDirs($this->modulesDirs)
			;
		}
		return $this;
	}
	
	protected function initRequest()
	{
		if(!$this->request) {
			$this->request = new Request();
		}
		return $this;
	}
	
	protected function setRequestAsAService()
	{
		$this->serviceContainer->set('request', $this->request);
	}
	
	protected function loadKernelListeners()
	{
		$services = $this->serviceContainer->findTaggedServiceIds('kernel.listener');
		if(!empty($services) && is_array($services)) {
			$ev = $this->serviceContainer->get('event_dispatcher');
			FileLogger::directLog('Loading kernel listeners ...');
			foreach($services as $id => $attributes) {
				if(!empty($attributes)) {
					FileLogger::directLog("   $id");
					$this->serviceContainer->get($id)->register($ev);
				}
			}
		}
	}
	
	protected function loadServicesStarter()
	{
		if($this->serviceStarter) {
			$services = $this->serviceContainer->findTaggedServiceIds('service_starter.register');
			if(!empty($services) && is_array($services)) {
				FileLogger::directLog('Loading services starter ...');
				foreach($services as $id => $attributes) {
					if(!empty($attributes)) {
						FileLogger::directLog("   $id");
						$this->serviceStarter->addService($id, 'saveState', 'restoreState');
					}
				}
			}
		}
	}
	
	public function bootstrap()
	{
		// if the application has already been bootstraped
		if($this->bootstraped) {
			return $this;
		}
		
		// initialise the service container
		$this->initServiceContainer();
		
		// set the class loader as a service
		$this->setClassLoaderAsAService();
		
		// set the cache manager as a service
		$this->setCacheManagerAsAService();
		
		// initialise the module manager
		$this->initModuleManager();
		$this->setModuleManagerAsAService();
		
		// initialise the request
		$this->initRequest();
		$this->setRequestAsAService();
		
		// in not in debug
		if(!$this->debug) {
			// restore the config
			$this->restoreState();
		}
		
		// if the application has not been bootstraped
		if(!$this->bootstraped) {
			
			// load modules
			$this->moduleManager->loadModules();
			
			// load kernel listeners
			$this->loadKernelListeners();
			
			// load services starter
			$this->loadServicesStarter();
			
			// application is bootstraped
			$this->bootstraped = true;
		}
		
		return $this;
	}
	
	protected function isCli()
	{
		return !isset($_SERVER['HTTP_HOST']);
	}
	
	public function setModules(array $modules)
	{
		$this->modules = $modules;
		return $this;
	}
	
	public function setModulesDirs(array $modulesDirs)
	{
		$this->modulesDirs = $modulesDirs;
		return $this;
	}
	
	public function run()
	{
		// bootstrap the application
		$this->bootstrap();
		/*
		echo '<pre>' . print_r(
			array_keys($this->serviceContainer
				->get('class_loader')
				->getTempCache()
			), true)
		. '</pre>';
		die();
		*/
		// logger
		//$logger = $this->serviceContainer->get('logger');
		
		// get the http handler
		$handler = $this->serviceContainer->get('http_kernel');
		
		// get the request
		$request = $this->serviceContainer->get('request');
		
		// handle the request and produce the http response
		$response = $handler->handle($request);
		
		// send the response
		$response->send();
		
		return $this;
	}
}