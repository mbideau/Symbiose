<?php

namespace Symbiose\Component\EventDispatcher;

use Symfony\Bundle\FrameworkBundle\EventDispatcher as BaseEventDispatcher,
	Symbiose\Component\Service\ServiceContainerAwareInterface,
	Symbiose\Component\Service\ServiceContainer
;

class EventDispatcher
	extends BaseEventDispatcher
	implements ServiceContainerAwareInterface
{
	protected $serviceContainer;
	
	public function __construct(ServiceContainer $sc = null)
	{
		if(!empty($sc)) {
			$this->setServiceContainer($sc);
		}
	}
	
	public function setServiceContainer(ServiceContainer $sc)
	{
		$this->serviceContainer = $sc;
		return $this;
	}
}