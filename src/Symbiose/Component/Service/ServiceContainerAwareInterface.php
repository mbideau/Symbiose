<?php

namespace Falcon\Site\Component\Service;

use Falcon\Site\Component\Service\ServiceContainer;

interface ServiceContainerAwareInterface
{
	public function setServiceContainer(ServiceContainer $sc);
}