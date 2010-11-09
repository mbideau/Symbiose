<?php

namespace Falcon\Site\Component\Service\Dumper;

use Symfony\Component\DependencyInjection\Dumper\PhpDumper as BasePhpDumper;

/**
 * PhpDumper dumps a service container as a PHP class
 */
class PhpDumper
	extends BasePhpDumper
{
	protected $defaultClass = 'DumpedServiceContainer';
	protected $defaultBaseClass = 'ServiceContainer';
	protected $defaultUsedClasses = array(
		'Falcon\\Site\\Component\\Service\\ServiceContainer'
	);
	protected $usedClasses = array();
	
	protected function addUsedClasses(array $usedClasses)
	{
		$this->usedClasses = array_merge($this->usedClasses, $usedClasses);
	}
	
    /**
     * Dumps the service container as a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *  * used_classes: The classes used
     *
     * @param  array  $options An array of options
     *
     * @return string A PHP class representing of the service container
     */
    public function dump(array $options = array())
    {
        $options = array_merge(array(
            'class'      => $this->defaultClass,
            'base_class' => $this->defaultBaseClass,
        	'used_classes' => $this->defaultUsedClasses
        ), $options);
        
        $this->addUsedClasses($options['used_classes']);
        
        return parent::dump($options);
    }

    protected function startClass($class, $baseClass)
    {
        $bagClass = $this->container->isFrozen() ? 'FrozenParameterBag' : 'ParameterBag';

        $useStatements = '';
        if(!empty($this->usedClasses)) {
        	foreach($this->usedClasses as $usedClass) {
        		$useStatements .= "use $usedClass;\n";
        	}
        }
        return <<<EOF

$useStatements
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\\$bagClass;

/**
 * $class
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class $class
	extends $baseClass
{
    protected \$shared = array();

EOF;
    }
}
