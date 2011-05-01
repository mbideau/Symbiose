<?php

namespace Modules\Website\Classes;

use Doctrine\Common\Cache\AbstractCache;
use Symbiose\Component\Caching\CacheManager; 

class DoctrineFileCache
	extends AbstractCache
{
	protected static $cacheId = 'doctrine';
	
	protected $cacheManager;
	protected $cleanedIds = array();
	
	public function __construct($cacheManager)
	{
		$this->cacheManager = $cacheManager;
	}
	
	protected function cleanId($id)
	{
		if(!array_key_exists($id, $this->cleanedIds)) {
			$this->cleanedIds[$id] = preg_replace('#[^a-zA-Z0-9_]+#', '', $id);
		}
		return $this->cleanedIds[$id];
	}
	
	protected function _doFetch($id)
	{
		// clean id name
		$cleanId = $this->cleanId($id);
		
		if($this->cacheManager->hasCache(self::$cacheId)) {
			$cache = $this->cacheManager->getCache(self::$cacheId);
			if($cache->test($cleanId)) {
				return $cache->load($cleanId);
			}
		}
		return false;
	}
	
	protected function _doContains($id)
	{
		// clean id name
		$cleanId = $this->cleanId($id);
		
		if($this->cacheManager->hasCache(self::$cacheId)) {
			$cache = $this->cacheManager->getCache(self::$cacheId);
			return $cache->test($cleanId);
		}
		return false;
	}
	
	protected function _doSave($id, $data, $lifeTime = false)
	{
		// clean id name
		$cleanId = $this->cleanId($id);
		
		if($this->cacheManager->hasCache(self::$cacheId)) {
			$cache = $this->cacheManager->getCache(self::$cacheId);
			return $cache->save($data, $cleanId, array(), $lifeTime);
		}
		return false;
	}
	
	protected function _doDelete($id)
	{
		// clean id name
		$cleanId = $this->cleanId($id);
		
		if($this->cacheManager->hasCache(self::$cacheId)) {
			$cache = $this->cacheManager->getCache(self::$cacheId);
			return $cache->remove($cleanId);
		}
		return false;
	}
	
	public function getIds()
	{
		if($this->cacheManager->hasCache(self::$cacheId)) {
			$cache = $this->cacheManager->getCache(self::$cacheId);
			$ids = $cache->getIds();
			if(is_array($ids)) {
				$validIds = array();
				$cleanedIds = array_flip($this->cleanedIds);
				foreach($ids as $id) {
					// restore the original id name
					$validIds[] = array_key_exists($id, $cleanedIds) ? $cleanedIds[$id] : $id;
				}
				//var_dump(array('ids' => $ids, 'validIds' => $validIds));
				return $validIds;
			}
		}
		return array();
	}
}