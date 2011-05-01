<?php

namespace Symbiose\Component\Entity;

use Symbiose\Component\Entity\EntityInterface,
	RuntimeException as Exception
;

abstract class Entity
	implements EntityInterface
{
	static protected $requiredFields = array();
	
	static public function getRequiredFields()
	{
		return self::$requiredFields;
	}
	
    public function __construct(array $parameters = array())
	{
		$this->setValues($parameters);
	}
	
	public function setValues(array $parameters = array())
	{
		if(!empty($parameters)) {
			$reflectionClass = new \ReflectionClass(get_class($this));
			foreach($parameters as $key => $value) {
				if($reflectionClass->hasProperty($key)) {
					if($value === '') {
						$value = null;
					}
					$this->$key = $value;
				}
			}
		}
		return $this;
	}
	
	public function correctValues()
	{
		// get entity properties
		$properties = self::getEntityProperties($this);
		if(!empty($properties)) {
			foreach($properties as $p) {
				$name = $p->getName();
				$this->correctValue($name);
			}
		}
	}
	
	protected function correctValue($name)
	{
		return $this->$name;
	}
	
	/*
	static public function getFieldsNames($keys = false)
	{
		return self::_getFieldsNames(__CLASS__, $keys);
	}
	*/
	static protected function _getFieldsNames($entityClassname, $keys = false)
	{
		$fields = array();
		// get entity properties
		$properties = $this->getEntityProperties($entityClassname);
		if(!empty($properties)) {
			if(!$keys) {
				foreach($properties as $p) {
					$fields[$p->getName()] = null;
				}
			}
			else {
				foreach($properties as $p) {
					$fields[] = $p->getName();
				}
			}
		}
		return $fields;
	}
	
	public function toArray($entity, $excludeEmptyValues = false)
	{
		$array = array();
		$fields = self::_getFieldsNames(get_class($entity));
		foreach($fields as $field) {
			$key = $field;
			$value = $this->$key;
			if(!$excludeEmptyValues || !empty($value)) {
				$array[$key] = $value;
			}
		}
		return $array;
	}
	
	static public function isEntity($e)
	{
		if(is_object($e)) {
			$class = get_class($e);
			return strpos($class , 'Proxies\\Entities') === 0 || strpos($class, 'Entities\\') === 0;
		}
		return false;
	}
	
	static public function isEntityCollection($c)
	{
		if(is_array($c)) {
			if(!empty($c)) {
				foreach($c as $k => $v) {
					if(!self::isEntity($v)) {
						return false;
					}
				}
			}
			return true;
		}
		elseif(is_object($c)) {
			$class = get_class($c);
			return $class == 'Doctrine\\Common\\Collections\\ArrayCollection' || $class == 'Doctrine\\ORM\\PersistentCollection';
		}
		return false;
	}
	
	static public function getEntityName($e, $safe = false)
	{
		if(self::isEntity($e)) {
			$class = get_class($e);
		}
		elseif(is_string($e)) {
			$class = $e;
		}
		if(isset($class)) {
			if(strpos($class , 'Proxies\\Entities') !== false) {
				$ename = substr($class, strlen('Proxies\\Entities'));
			}
			else {
				if(strpos($class , '\\') !== false) {
					$ename = substr(strrchr($class, '\\'), 1);
				}
				else {
					$ename = $class;
				}
			}
			if($safe) {
				$ename = preg_replace('#[^0-9a-zA-Z]#', '', $ename);
				$ename = strtolower(preg_replace('#([A-Z])#', '_\1', $ename));
				$ename = preg_replace('#^_#', '', $ename);
			}
			return $ename;
		}
		return false;
	}
	
	static public function getEntityProperties($entity)
	{
		if(empty($entity)) {
			throw new Exception(__FUNCTION__ . " : entity is empty");
		}
		elseif(!self::isEntity($entity)) {
			throw new Exception(__FUNCTION__ . " : entity is not a valid entity");
		}
		$entityClass = get_class($entity);
		// is it is a proxy
		if(strpos($entityClass , 'Proxies\\Entities') === 0) {
			$entityClass = get_parent_class($entityClass);
		}
		$reflectionClass = new \ReflectionClass($entityClass);
		$r_properties = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);
		$properties = array();
		foreach($r_properties as $property) {
			// don't not take in account static properties
			if($property->isStatic()) {
				continue;
			}
			$properties[] = $property;
		}
		return $properties;
	}
}