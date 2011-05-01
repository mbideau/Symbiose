<?php

namespace Symbiose\Component\Entity;

use Symbiose\Component\Entity\Entity;

class Analyser
{
	protected $cache;
	
	public function getFiles($entity)
	{
		$files = array();
		// get files fields
		$filesFields = $this->getFilesFields($entity);
		if(!empty($filesFields)) {
			foreach($filesFields as $fileField) {
				// value method
				$methodName = 'get' . ucfirst($fileField);
				$value = $entity->$methodName();
				if($value) {
					$files[] = $value;
				}
			}
		}
		return $files;
	}
	
	public function getAssociatedEntities($entity)
	{
		$entities = array();
		// get mapping fields
		$mappingsFields = $this->getMappingsFields($entity);
		if(!empty($mappingsFields)) {
			foreach($mappingsFields as $mappingsField) {
				// value method
				$methodName = 'get' . ucfirst($mappingsField);
				$value = $entity->$methodName();
				if(!empty($value)) {
					$newEntities = array();
					if(Entity::isEntityCollection($value)) {
						$newEntities = $value;
					}
					elseif(Entity::isEntity($value)) {
						$newEntities = array($value);
					}
					if(!empty($newEntities)) {
						$entities = array_merge($entities, $newEntities);
					}
				}
			}
		}
		return $entities;
	}
	
	public function getFilesFields($entity)
	{
		return $this->getFieldList('files', $entity);
	}
	
	public function getMappingsFields($entity)
	{
		return $this->getFieldList('mappings', $entity);
	}
	
	public function getArraysFields($entity)
	{
		return $this->getFieldList('arrays', $entity);
	}
	
	protected function getFieldList($listName, $entity)
	{
		// get entity name
		$entityName = Entity::getEntityName($entity, true);
		
		// if the files fields are not yet stored in cache
		if(!array_key_exists($entityName, $this->cache) || !array_key_exists($listName, $this->cache[$entityName])) {
			// analyse the entity
			$this->analyse($entityName);
		}
		
		// retrieve them from the cache
		return $this->cache[$entityName][$listName];
	}
	
	protected function analyse($entityName)
	{
		// if the entity datas are not yet stored in cache
		if(!array_key_exists($entityName, $this->cache)) {
			// init the cache
			$this->cache[$entityName] = array();
			
			// arrays
			$arraysFields = array();
			// mappings
			$mappingsFields = array();
			// files
			$filesFields = array();
			
			// get entity properties
			$properties = Entity::getEntityProperties($entity);
			
			// foreach property of the entity
			foreach($properties as $property) {
				// property name
				$propertyName = $property->getName();
				// property class comments
				$classComments = $property->getDocComment();
				// get type
				$type = $this->getPropertyType($classComments);
				// get backend type
				$backendType = $this->getPropertyBackendType($classComments);
				// is file
				if($backendType == 'file') {
					// add the field to the list
					$filesFields[] = $propertyName;
				}
				elseif($type == 'mapping') {
					// add the field to the list
					$mappingsFields[] = $propertyName;
				}
				elseif($type == 'array') {
					// add the field to the list
					$arraysFields[] = $propertyName;
				}
			}
			
			// save the lists to the cache
			$this->cache[$entityName]['files'] = $filesFields;
			$this->cache[$entityName]['mappings'] = $mappingsFields;
			$this->cache[$entityName]['arrays'] = $arraysFields;
		}
	}
	
	protected function getPropertyType($classComments)
	{
		$type = null;
		// basic type
		if(preg_match('#@Column\(type="(\w+)"#', $classComments, $matches)) {
			$type = $matches[1];
		}
		// mapping type
		elseif(preg_match('#@(\w+)\(targetEntity="(\w+)"#', $classComments)) {
			$type = 'mapping';
		}
		return $type;
	}
	
	protected function getPropertyBackendType($classComments)
	{
		if(preg_match('#@backend\(type="(\w+)"#', $classComments, $matches)) {
			return $matches[1];
		}
		return null;
	}
}