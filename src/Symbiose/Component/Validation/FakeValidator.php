<?php

namespace Symbiose\Component\Validation;

use Symfony\Component\Validator\ValidatorInterface,
	Symfony\Component\Validator\Constraint
;

/**
 * Fake validator
 * Trick to bypass the required validator in the form::__construct() function
 * Validation is done, instead, at the entity level (just before the persistancy)
 * @author Michael Bideau
 *
 */
class FakeValidator
	implements ValidatorInterface
{
	public function validate($object, $groups = null) {}
    public function validateProperty($object, $property, $groups = null) {}
    public function validatePropertyValue($class, $property, $value, $groups = null) {}
    public function validateValue($value, Constraint $constraint, $groups = null) {}
}