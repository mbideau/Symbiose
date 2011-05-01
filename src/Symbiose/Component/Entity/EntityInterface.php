<?php

namespace Symbiose\Component\Entity;

use Symfony\Component\Validator\Mapping\ClassMetadata;

interface EntityInterface
{
	public function setValues(array $parameters = array());
	
	public function correctValues();
	
	public function equals(EntityInterface $e, $strictly = false);
	
	public function __toString();
	
	/*
	 * Entities must implements that function, but
	 * if this function is placed in this interface, it create a bug
	 * because the classmetadata tries to load this function and fails
	 * static public function loadValidatorMetadata(ClassMetadata $metadata);
	 */
}