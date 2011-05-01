<?php

namespace Symbiose\Component\Entity;

use Symbiose\Component\Service\ServiceContainerAware,
	Symbiose\Component\Entity\Entity,
	RuntimeException as Exception,
	Zend\Search\Lucene\Document,
	Zend\Search\Lucene\Index\Term,
	Zend\Search\Lucene\Field
;

class Listener
	extends ServiceContainerAware
{
	/*
	protected $events = array(
		'prePersist',
		'postPersist',
		'preUpdate',
		'postUpdate',
		'postRemove'
	);*/
	
	protected $searchManagerService;
	protected $validatorService;
	protected $publicFilesManagerService;
	protected $loggerService;
	protected $entityAnalyserService;
	
	public function prePersist($eventArgs)
    {
		$entity = $eventArgs->getEntity();
		// correct entity values
		$entity->correctValues();
    	// validate the entity
		if($errors = $this->validateEntity($entity)) {
			$entityString = is_object($entity) ? get_class($entity) : gettype($entity);
			throw new Exception("Entity '$entityString' is not valid.\nErrors:\n" . implode("\n", $errors));
		}
    }
    
	public function preUpdate($eventArgs)
    {
    	$entity = $eventArgs->getEntity();
    	// correct entity values
		$entity->correctValues();
    	// validate the entity
		if($errors = $this->validateEntity($entity)) {
			$entityString = is_object($entity) ? get_class($entity) : gettype($entity);
			throw new Exception("Entity '$entityString' is not valid.\nErrors:\n" . implode("\n", $errors));
		}
    }
    
	public function postPersist($eventArgs)
    {
        $entity = $eventArgs->getEntity();
    	//$this->addToSearch($entity, true);
    }
    
	public function postUpdate($eventArgs)
    {
        $entity = $eventArgs->getEntity();
    	//$this->addToSearch($entity, false);
    }
    
	public function postRemove($eventArgs)
    {
        $entity = $eventArgs->getEntity();
       // $this->removeFromSearch($entity);
    	$this->removeFiles($entity);
    }
    
    protected function addToSearch($entity, $new)
    {
    	// entity name
		$entityName = Entity::getEntityName($entity, true);
    	
    	// get search manager service
		$sm = $this->getSearchManagerService();
		if(!$sm) {
			throw new Exception("Failed to get search manager service");
		}
		// get search index
		$sname = $entityName;
		$sindex = $sm->getIndex($sname);
		if($sindex) {
			// get entity id
			$eid = $entity->getId();
			if(!$eid) {
				throw new Exception(__FUNCTION__ . " : entity id is empty");
			}
	
			if(!$new) {
				// to update it we need to remove it from search index
				$term = new Term($eid, 'id');
				$hits = $sindex->termDocs($term);
				foreach ($hits as $hit) {
					$sindex->delete($hit);
				}
			}
			
			// add to search index
			$doc = $this->createSearchDocFromEntity($entity);
			if($doc) {
				$sindex->addDocument($doc);
			}
			$sindex->commit();
		}
    }
    
	protected function removeFromSearch($entity, $new)
    {
    	// entity name
		$entityName = Entity::getEntityName($entity, true);
    	
    	// get search manager service
		$sm = $this->getSearchManagerService();
		if(!$sm) {
			throw new Exception("Failed to get search manager service");
		}
		// get search index
		$sname = $entityName;
		$sindex = $sm->getIndex($sname);
		if($sindex) {
			// get entity id
			$eid = $entity->getId();
			if(!$eid) {
				throw new Exception(__FUNCTION__ . " : entity id is empty");
			}
	
			// to update it we need to remove it from search index
			$term = new Term($eid, 'id');
			$hits = $sindex->termDocs($term);
			foreach ($hits as $hit) {
				$sindex->delete($hit);
			}
			
			$sindex->commit();
		}
    }
    
    public function removeFiles($entity)
    {
    	// get the entity analyser service
		$ea = $this->getEentityAnalyserService();
		if(!$ea) {
			throw new Exception("Failed to get entity analyser service");
		}
		
		// get entity id
		$eid = $entity->getId();
		if(!$eid) {
			throw new Exception(__FUNCTION__ . " : entity id is empty");
		}
		
		// get entity files
		$files = $ea->getFiles($entity);
		
		// if there are files
		if(!empty($files)) {
			// get public files manager
			$pfm = $this->getPublicFilesManagerService();
			if(!$pfm) {
				throw new Exception("Failed to get public files manager service");
			}
			// for each file to delete
			foreach($files as $file) {
				// get file path
				$filePath = $pfm->getFilePath($value);
				// if the file exists, delete it
				if(file_exists($filePath)) {
					try {
						if(!@unlink($filePath)) {
							// @todo throw exception
						}
					}
					catch(\Exception $e) {
						// @todo throw exception
					}
				}
			}
		}
    }
    
	public function createSearchDocFromEntity($e)
	{
		$doc = null;
		if(!empty($e) && Entity::isEntity($e)) {
			$doc = new Document();
			$eclass = get_class($e);
			// get entity properties
			$properties = Entity::getEntityProperties($e);
			// for each property
			foreach($properties as $property) {
				$field = $this->getDocField($e, $property);
				if($field) {
					$doc->addField($field);
				}
			}
		}
		return $doc;
	}
	
	protected function getDocField($entity, $property)
	{
		$field = null;
		if($property) {
			// property name
			$propertyName = $property->getName();
			// value
			$methodName = 'get' . ucfirst($propertyName);
			$value = $entity->$methodName();
			
			if(is_array($value) || Entity::isEntityCollection($value)) {
				$newValue = array();
				foreach($value as $k => $v) {
					$newValue[] = (string) $v;
				}
				$value = implode("\n", $newValue);
			}
			elseif($value instanceof \DateTime) {
				$value = $value->format('d/m/Y H:i:s');
			}
			elseif(is_bool($value)) {
				$value = $value ? 'true' : 'false';
			}
			
			$value = (string) $value;
			// @todo try to test iconv
			try {
				$value = iconv("ISO-8859-1", "UTF-8//TRANSLIT//IGNORE", $value);
			}
			catch(\Exception $e) {
				var_dump(array(
					'value' => $value,
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				)); die();
				//$value = mb_convert_encoding($value, 'UTF-8', 'auto');
			}
			
			$field = Field::Text($propertyName, $value);
		}
		return $field;
	}
	
	protected function validateEntity($entity)
	{
		// get validator service
		$validator = $this->getValidatorService();
		if(!$validator) {
			throw new Exception("Failed to get validator service");
		}
    	
		// get the violations of entities datas
		$violations = $validator->validate($entity);
		$errors = $violations;
		if(!empty($violations) && method_exists($violations, 'getIterator')) {
			$violations = $violations->getIterator();
			$errors = $violations;
			if(!empty($errors)) {
				$errors = array();
				foreach($violations as $violation) {
					$errors[] = $violation->getMessage() . ' (' . $violation->getPropertyPath() . ':' . var_export($violation->getInvalidValue(), true) . ')';
				}
			}
		}
		return $errors;
	}
}