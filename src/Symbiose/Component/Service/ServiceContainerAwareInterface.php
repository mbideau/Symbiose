<?php

namespace Symbiose\Component\Service;

use Symbiose\Component\Service\ServiceContainer;

interface ServiceContainerAwareInterface
{
	public function setServiceContainer(ServiceContainer $sc);
}