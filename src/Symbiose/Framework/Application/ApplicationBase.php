<?php

namespace Symbiose\Framework\Application;

use Symbiose\Framework\Application\ApplicationInterface,
	Zend\Log\Logger,
	Zend\Log\Writer\Stream,
	Zend\Log\Filter\Priority,
	Zend\Log\Formatter\Simple as Formatter,
	Symbiose\Framework\Module\ModuleManagerStateful as ModuleManager,
	Symbiose\Component\Service\ServiceContainerStateful as ServiceContainer,
	Symbiose\Framework\Application\Exception\FatalErrorException,
	Symbiose\Framework\Application\Exception\NonFatalErrorException,
	Zend\Loader\ClassMapAutoloader as ClassLoader
;

class ApplicationBase
	implements  ApplicationInterface
{
	/**
	 * The absolute path to site root directory
	 * @var string
	 */
	protected $siteRoot;
	
	/**
	 * The absolute path to application directory
	 * @var string
	 */
	protected $applicationPath;
	
	/**
	 * The absolute path to the data directory
	 * @var string
	 */
	protected $dataPath;
	
	/**
	 * The absolute path to log directory
	 * @var string
	 */
	protected $logPath;
	
	/**
	 * The absolute path to cache directory
	 * @var string
	 */
	protected $cachePath;
	
	/**
	 * The absolute path to the modules directory
	 * @var string
	 */
	protected $modulesPath;

	/**
	 * The absolute path to the library directory
	 * @var string
	 */
	protected $libraryPath;
	
	/**
	 * The absolute path to preloader directory
	 * @var string
	 */
	protected $preloaderPath;
	
	/**
	 * Used to detected if the application is already bootstraped
	 * @var bool
	 */
	protected $bootstraped;
	
	/**
	 * The class loader instance
	 * @var object
	 */
	protected $classLoader;
	
	/**
	 * The logger instance
	 * @var object
	 */
	protected $logger;
	
	/**
	 * List of modules direcotry to load
	 * @var array
	 */
	protected $modules;
	
	/**
	 * The module manager instance
	 * @var object
	 */
	protected $moduleManager;
	
	/**
	 * The system module name
	 * @var string
	 */
	protected $systemModuleName = 'system';
	
	/**
	 * The service container instance
	 * @var object
	 */
	protected $serviceContainer;
	
	/**
	 * The name of the current domain requested
	 * @var string
	 */
	protected $domain;

	/**
	 * The constructor - register class autoloader(s)
	 * @param	int		$envCode The environment code
	 * @param	string	$envText The environment text
	 * @return	PavillonApplication
	 */
	public function __construct($envCode, $envText = null)
	{
		$this->environmentCode = $envCode;
		$this->environmentText = $envText;
		if(!$this->environmentText) {
			switch($this->environmentCode) {
				case self::ENV_DEVELOPMENT:
					$this->environmentText = 'development';
					break;
				case self::ENV_TESTING:
					$this->environmentText = 'testing';
					break;
				case self::ENV_PRODUCTION:
					$this->environmentText = 'production';
					break;
			}
		}
		return $this;
	}
	
	/**
	 * Bootstrap the application
	 * @return PavillonApplication
	 */
	public function bootstrap()
	{
		// if the application has not already been bootstraped
		if(!$this->bootstraped) {
			$this->bootstraptChain();
			$this->moduleManager->loadModules();
			$this->bootstraped = true;
			// load kernel listeners
			$this->loadKernelListeners();
		}
		return $this;
	}
	
	/**
	 * Do the normal bootstraping chain
	 * @return PavillonApplication
	 */
	protected function bootstraptChain()
	{
		$this
				->initErrorHandler()
				->resetIncludePath()
				->initPathes()
				->detectDomain()
				->initClassLoader()
				->initLogger()
				->initModules()
				->initServiceContainer()
				->initModuleManager()
			;
		return $this;
	}
	
	/**
	 * Run the application
	 * @return void
	 */
	public function run()
	{
		if(!$this->bootstraped) {
			$this->bootstrap();
		}
		$response = $this->serviceContainer->getHttpKernelService()->handle(
			$this->serviceContainer->getRequestService()
		);
		! $this->logger ?: $this->logger->debug("Application: Got response:\n" . (is_object($response) ? get_class($response) : $response));
		! $this->logger ?: $this->logger->debug('Application: Sending response ...');
		$response->send();
		! $this->logger ?: $this->logger->debug('Application: Response sent');
	}
	
	/**
	 * Stop the application
	 * Release/unset all resources
	 * @return PavillonApplication
	 */
	public function stop()
	{
		unset($this->serviceContainer);
		unset($this->logger);
		unset($this->moduleManager);
		return $this;
	}
	
	/**
	 * Replace the default error handler by the application function
	 * @return PavillonApplication
	 */
	protected function initErrorHandler()
	{
		set_error_handler(array($this, 'handleError'));
		return $this;
	}
	
	/**
	 * Handle errors and convert them into exception (fatal or non-fatal)
	 * @param int $code
	 * @param string $message
	 * @param string $file
	 * @param int $line
	 * @param string $context
	 * @throws NonFatalErrorException
	 * @throws FatalErrorException
	 */
	public function handleError($code, $message, $file, $line, $context)
	{
		// Determine if this error is one of the enabled ones in php config (php.ini, .htaccess, etc)
		$error_is_enabled = (bool)($code & ini_get('error_reporting'));
		// -- FATAL ERROR
		// throw a FatalErrorException, to be handled by whatever Exception handling logic is available in this context
		if(in_array($code, array(E_USER_ERROR, E_RECOVERABLE_ERROR)) && $error_is_enabled ) {
			throw new FatalErrorException($message, 0, $code, $file, $line);
		}
		// -- NON-FATAL ERROR/WARNING/NOTICE
		// throw a NonFatalErrorException, to be handled by whatever Exception handling logic is available in this context
		throw new NonFatalErrorException($message, 0, $code, $file, $line);
	}
	
	/**
	 * Reset the include path
	 * @todo useless ?
	 * @return PavillonApplication
	 */
	protected function resetIncludePath()
	{
		set_include_path('');
		return $this;
	}
	
	/**
	 * Build every required application pathes
	 * @return PavillonApplication
	 */
	protected function initPathes()
	{
		$this->siteRoot = $this->detectSiteRoot();
		$this->applicationPath = $this->siteRoot;
		$this->dataPath = $this->siteRoot . '/data';
		$this->modulesPath = $this->applicationPath . '/modules';
		$this->libraryPath = $this->siteRoot . '/library';
		$this->logPath = $this->dataPath . '/log';
		$this->cachePath = $this->dataPath . '/cache';
		$this->preloaderPath = $this->applicationPath . '/preloader/build';
		return $this;
	}
	
	/**
	 * Try to get the site root by itself
	 * @return string
	 */
	protected function detectSiteRoot()
	{
		return dirname(dirname($_SERVER['SCRIPT_FILENAME']));
	}
	
	/**
	 * Detect the domain requested and set it
	 * @return PavillonApplication
	 */
	protected function detectDomain()
	{
		if(getenv('DOMAIN')) {
			$this->domain = preg_replace('#^www\.#', '', getenv('DOMAIN'));
		}
		if(empty($this->domain)) {
			throw new Exception("you must specify a domain as environment variable 'domain'");
		}
		return $this;
	}
	
	/**
	 * Build the class loader instance
	 */
	protected function initClassLoader()
	{
		$this->classLoader = new ClassLoader();
		$this->classLoader->register();
		return $this;
	}
	
	/**
	 * Build the logger
	 * Use standard file logger
	 * @return PavillonApplication
	 */
	protected function initLogger()
	{
		$this->logger = new Logger();
		// enable firephp logging only if special header was sent
		if(isset($_SERVER['HTTP_X_SYMBIOSE_DEBUG']) && $_SERVER['HTTP_X_SYMBIOSE_DEBUG'] == 1) {
			define('INSIGHT_CONFIG_PATH', $this->modulesPath . '/' . $this->systemModuleName . '/config/firephp/package.json');
			//define('INSIGHT_DEBUG', true);
			define('FIREPHP_ACTIVATED', true);
			// include firephp init
			include $this->libraryPath . '/FirePhp/FirePHP/Init.php';
			// include firephp preloader
			include $this->preloaderPath . '/preload.firephp-namespaced.php';
			$firephp = new \Symbiose\Component\Logging\Writer\FirePHP();
			$this->logger->addWriter($firephp);
			// stream writer to debug
			$steeamWriter = new Stream($this->logPath . '/application-debug.log');
			$formatter = new Formatter("%timestamp% %priorityName%: %message%\n");
			$steeamWriter->setFormatter($formatter);
			$this->logger->addWriter($steeamWriter);
			// log current environment and domain
			$this->logger->info('Environment: ' . $this->environmentText . ' (code: ' . $this->environmentCode . ')');
			$this->logger->info('Domain: ' . $this->domain);
		}
		// else use file(stream) writers
		else {
			// log errors
			$steeamWriter = new Stream($this->logPath . '/application-error.log');
			$filterError = new Priority(4);
			$steeamWriter->addFilter($filterError);
			$formatter = new Formatter("%timestamp% %priorityName%: %message%\n");
			$steeamWriter->setFormatter($formatter);
			$this->logger->addWriter($steeamWriter);
		}
		return $this;
	}
	
	/**
	 * Initialise modules
	 */
	protected function initModules()
	{
		$this->logger->debug('Application: init modules');
		$this->modules = array(
			'system' => $this->modulesPath . '/' . $this->systemModuleName
		);
		return $this;
	}
	
	/**
	 * Build a module manager
	 * Register modules
	 * @return PavillonApplication
	 */
	protected function initModuleManager()
	{
		$this->logger->debug('Application: init module maager');
		$this->moduleManager = new ModuleManager($this->logger);
		$this->moduleManager
			->setServiceContainer($this->serviceContainer)
			->registerModules($this->modules)
		;
		$this->serviceContainer->set('module_manager', $this->moduleManager);
		return $this;
	}
	
	/**
	 * Build a service container
	 * By restoring it, or creating a new
	 * @return PavillonApplication
	 */
	protected function initServiceContainer()
	{
		$this->logger->debug('Application: init service container');
		if(empty($this->serviceContainer)) {
			$this->serviceContainer = new ServiceContainer();
		}
		$this->serviceContainer->setParameter('environment.code', $this->environmentCode);
		$this->serviceContainer->setParameter('kernel.debug', $this->environmentCode != self::ENV_PRODUCTION);
		$this->serviceContainer->setParameter('environment.text', $this->environmentText);
		$this->serviceContainer->setParameter('path.site_root', $this->siteRoot);
		$this->serviceContainer->setParameter('path.preloader', $this->preloaderPath);
		$this->serviceContainer->setParameter('path.log', $this->logPath);
		$this->serviceContainer->set('application', $this);
		$this->serviceContainer->set('class_loader', $this->classLoader);
		$this->serviceContainer->set('logger', $this->logger);
		return $this;
	}
	
	/**
	 * Load the components that msut listen to kernel events
	 * @return PavillonApplication
	 */
	protected function loadKernelListeners()
	{
		$services = $this->serviceContainer->findTaggedServiceIds('kernel.listener');
		if(!empty($services) && is_array($services)) {
			$ev = $this->serviceContainer->get('event_dispatcher');
			$this->logger->debug('Application: Loading kernel listeners ...');
			foreach($services as $id => $attributes) {
				if(!empty($attributes)) {
					$this->logger->debug("   $id");
					$this->serviceContainer->get($id)->register($ev);
				}
			}
		}
		return $this;
	}
	
	public function getServiceContainer() { return $this->serviceContainer; }
	public function getDomain() { return $this->domain; }
}
