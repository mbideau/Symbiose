<?php

namespace Symbiose\Framework\Application;

use Symbiose\Framework\Application\ApplicationBase,
	Symbiose\Component\Object\StatefulInterface,
	Symbiose\Component\ClassLoader\ClassLoaderStateful as ClassLoader,
	Symbiose\Component\Service\ServiceContainerStateful as ServiceContainer,
	Symbiose\Framework\Module\ModuleManagerStateful as ModuleManager,
	Symbiose\Component\Caching\CacheManagerStateful as CacheManager
;

abstract class ApplicationBaseStateful
	extends ApplicationBase
	implements StatefulInterface
{
	/**
	 * The cache manager instance
	 * @var object
	 */
	protected $cacheManager;
	
	/**
	 * The state of application
	 * @var string
	 */
	protected $state;
	
	/**
	 * List of the preloaders files
	 * @var array
	 */
	protected $preloaders;
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBase#bootstrap()
	 */
	public function bootstrap()
	{
		// if the application has not already been bootstraped
		if(!$this->bootstraped) {
			$this
				->bootstraptChain()
				->restoreState()
			;
			if(!$this->bootstraped) {
				$this
					->preloadClasses()
					->initModuleManager()
				;
				$this->moduleManager->loadModules();
			}
			$this->bootstraped = true;
			// load kernel listeners
			$this->loadKernelListeners();
		}
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBase#bootstraptChain()
	 */
	protected function bootstraptChain()
	{
		$this
				->initErrorHandler()
				->resetIncludePath()
				->initPathes()
				->initClassLoader()
				->initCacheManager()
				->initLogger()
				->initCacheManager()
				->initModules()
				->initServiceContainer()
			;
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBase#stop()
	 */
	public function stop()
	{
		$this->logger->info('Application: stoping ...');
		parent::stop();
		unset($this->cacheManager);
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBase#initClassLoader()
	 */
	protected function initClassLoader()
	{
		$this->classLoader = new ClassLoader();
		$this->classLoader->register();
		return $this;
	}
	
	/**
	 * Build a cache manager and load its configuration
	 * @return ApplicationBaseStateful
	 */
	protected function initCacheManager()
	{
		$this->logger->debug('Application: init cache manager');
		$this->cacheManager = new CacheManager();
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBase#initServiceContainer()
	 */
	protected function initServiceContainer()
	{
		parent::initServiceContainer();
		$this->serviceContainer->set('cache_manager', $this->cacheManager);
		return $this;
	}
	
	/**
	 * Preload classes required to build and merge
	 * service container and module manager
	 * @return ApplicationBaseStateful
	 */
	abstract protected function preloadClasses();
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Component/Object/Symbiose\Component\Object.StatefulInterface#getState()
	 */
	public function getState()
	{
		return serialize(array(
			'envCode' => $this->environmentCode,
			'modules' => $this->modules
		));
	}
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Component/Object/Symbiose\Component\Object.StatefulInterface#updateState()
	 */
	public function updateState()
	{
		$this->state = $this->getState();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Component/Object/Symbiose\Component\Object.StatefulInterface#setState()
	 */
	public function setState($state)
	{
		$this->state = $state;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Component/Object/Symbiose\Component\Object.StatefulInterface#restoreState()
	 */
	public function restoreState(array $parameters = array())
	{
		$this->logger->debug('Application: Restoring state ...');
		if($this->cacheManager) {
			// restoring cache manager base configuration
			$cacheTemplates = $this->getCacheManagerTemplates();
			if(!empty($cacheTemplates)) {
				$this->logger->debug('Application: got cache templates');
				$this->cacheManager->setCacheTemplates($cacheTemplates);
				// restoring the cache manager
				$this->logger->debug('Application: restoring cache manager ...');
				$this->cacheManager->restoreState();
				// restoring the class loader
				$this->logger->debug('Application: restoring class loader ...');
				$this->classLoader->restoreState(array('cache_manager' => $this->cacheManager));
				// restore preloader
				$this->logger->debug('Application: restoring preloader ...');
				$cache = $this->cacheManager->hasCache('preloaders') ? $this->cacheManager->getCache('preloaders') : null;
				if($cache) {
					if($cache->test('list')) {
						$preloaders = unserialize($cache->load('list'));
						if(!empty($preloaders) && $preloaders != $this->preloaders) {
							$this->preloaders = $preloaders;
							if($cache->test('prefixed')) {
								$id = 'preloaders' . '_' . 'prefixed';
								$file = $cache->getBackend()->getFile($id);
								include $file;
							}
							if($cache->test('namespaced')) {
								$id = 'preloaders' . '_' . 'namespaced';
								$file = $cache->getBackend()->getFile($id);
								include $file;
							}
						}
					}
				}
				// restoring service container (from the cache)
				$this->logger->debug('Application: restoring service container ...');
				$serviceContainer = ServiceContainer::getFromCache($this->cacheManager);
				if(!empty($serviceContainer)) {
					if(!empty($this->serviceContainer)) {
						// transfert services
						$servicesIds = array_diff($this->serviceContainer->getServiceIds(), array('service_container'));
						if(!empty($servicesIds)) {
							foreach($servicesIds as $sid) {
								$serviceContainer->set($sid, $this->serviceContainer->get($sid));
							}
						}
						// set it as the new service container
						$this->serviceContainer->set('service_container', $serviceContainer);
						$this->serviceContainer = $serviceContainer;
					}
					$this->serviceContainer = $serviceContainer;
					$this->serviceContainer->set('request', $this->serviceContainer->get('request.ori'));
					$this->logger->debug('Application: restoration is complete');
					// restore is complete
					$this->bootstraped = true;
				}
			}
		}
		return $this;
	}
	
	abstract protected function getCacheManagerTemplates();
	
	/**
	 * Retrieve the list of the modules preloaders
	 * @return array
	 */
	protected function getPreloadersList()
	{
		$preloaders = array();
		if($this->moduleManager) {
			$modules = $this->moduleManager->getModules();
			if(!empty($modules)) {
				foreach($modules as $m) {
					$preloaders = array_merge_recursive($preloaders, $m->getPreloaders());
				}
			}
		}
		return $preloaders;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Component/Object/Symbiose\Component\Object.StatefulInterface#saveState()
	 */
	public function saveState(array $parameters = array())
	{
		// get the current state
		$state = $this->getState();
		// if we need to save it (state is different)
		if($this->state != $state) {
			! $this->logger ?: $this->logger->debug('Application: Saving state ...');
			if($this->cacheManager) {
				// save cache manager templates
				! $this->logger ?: $this->logger->debug('Application: saving cache manager ...');
				$this->cacheManager->saveState();
				// save modules preloaders
				! $this->logger ?: $this->logger->debug('Application: saving preloaders ...');
				$preloaders = $this->getPreloadersList();
				if(!empty($preloaders) && $this->preloaders != $preloaders) {
					$contentPrefixed = '';
					if(isset($preloaders['prefixed']) && !empty($preloaders['prefixed']))  {
						foreach($preloaders['prefixed'] as $p) {
							$contentPrefixed .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($p)) . "\n";
						}
					}
					$contentNamespaced = '';
					if(isset($preloaders['namespaced']) && !empty($preloaders['namespaced']))  {
						foreach($preloaders['namespaced'] as $p) {
							$contentNamespaced .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($p)) . "\n";
						}
					}
					$cache = $this->cacheManager->hasCache('preloaders') ? $this->cacheManager->getCache('preloaders') : null;
					if($cache) {
						if(!empty($contentPrefixed)) {
							$cache->save('<' . "?php\n" . $contentPrefixed, 'prefixed');
						}
						if(!empty($contentNamespaced)) {
							$cache->save('<' . "?php\n" . $contentNamespaced, 'namespaced');
						}
						$cache->save(serialize($preloaders), 'list');
					}
				}
				// save class loader class map
				if($this->classLoader) {
					! $this->logger ?: $this->logger->debug('Application: saving class loader ...');
					$this->classLoader->saveState();
				}
				// save service container
				if($this->serviceContainer) {
					! $this->logger ?: $this->logger->debug('Application: saving service container ...');
					$this->serviceContainer->saveState();
				}
			}
		}
		return $this;
	}
	
	/**
	 * Destruction, try to save the state of the application
	 */
	public function __destruct()
	{
		! $this->logger ?: $this->logger->debug('Application: Application ending ...');
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
}