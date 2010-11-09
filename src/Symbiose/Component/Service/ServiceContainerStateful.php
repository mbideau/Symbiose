<?php

namespace Falcon\Site\Component\Service;

use Falcon\Site\Component\Service\ServiceContainer,
	Falcon\Site\Component\Object\StatefulInterface,
	Falcon\Site\Component\Service\Dumper\PhpDumper as ServiceContainerDumperPhP
;

class ServiceContainerStateful
	extends ServiceContainer
	implements StatefulInterface
{
	static protected $cacheId = 'service_container';
	static protected $cacheClassId = 'class';
	static protected $cacheClassname = 'ServiceContainerCache';
	
	protected $state;
	
	public function getState()
	{
		return serialize(array(
			$this->services,
			$this->parameterBag
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
		$sc = $this;
		// use the service container in the parameters to get the cache manager
		if(array_key_exists('service_container', $parameters)) {
			$sc = $parameters['service_container'];
		}
		$cacheManager = $sc->has('cache_manager') ? $sc->get('cache_manager') : null;
		// if the cache manager exists and has the cache for cache manager
		if($cacheManager && $cacheManager->hasCache(self::$cacheId)) {
			$cache = $cacheManager->getCache(self::$cacheId);
			if($cache->test(self::$cacheClassId)) {
				// prefix the id
				$id = self::$cacheId . '_' . self::$cacheClassId;
				$scFile = $cache->getBackend()->getFile($id);
				require $scFile;
				$scClassname = '\\' . self::$cacheClassname;
				// create a new service container (from cache class)
				$newSc = new $scClassname();
				// transfert services
				$servicesIds = array_diff($sc->getServiceIds(), array('service_container'));
				if(!empty($servicesIds)) {
					foreach($servicesIds as $sid) {
						$newSc->set($sid, $sc->get($sid));
					}
				}
				// set it as the new service container
				$sc->set('service_container', $newSc);
				// update the state
				$this->updateState();
				return true;
			}
		}
		return false;
	}
	
	public function saveState(array $parameters = array())
	{
		// get the current state
		$state = $this->getState();
		// if we need to save it (state is different)
		if($this->state != $state) {
			$cacheManager = $this->has('cache_manager') ? $this->get('cache_manager') : null;
			// use the service container in the parameters to get the cache manager
			if(array_key_exists('service_container', $parameters)) {
				$sc = $parameters['service_container'];
				if($sc->has('cache_manager')) {
					$cacheManager = $sc->get('cache_manager');
				}
			}
			// freeze (if not already frozen)
			if(!$this->isFrozen()) {
				$this->freeze();
			}
			// if the cache manager exists and has the cache for cache manager
			if($cacheManager && $cacheManager->hasCache(self::$cacheId)) {
				$cache = $cacheManager->getCache(self::$cacheId);
				// get a php dumper
				$scDumperPhp = new ServiceContainerDumperPhP($this);
				// dump the service container into a string
				$scContent = $scDumperPhp->dump(array(
					'class' => self::$cacheClassname,
					'base_class' => substr(strrchr(__CLASS__, '\\'), 1),
					'used_classes' => array(
						__CLASS__
					)
				));
				if(!empty($scContent) && is_string($scContent)) {
					$id = self::$cacheClassId;
					$content = "<?php\n$scContent";
					// save it to the cache
					if(!$cache->save($content, $id)) {
						throw new Exception("Can't write to cache '" . self::$cacheId . "'.\n".var_export(array('id' => $id, 'content' => $content), true));
					}
				}
			}
		}
		return $this;
	}
}