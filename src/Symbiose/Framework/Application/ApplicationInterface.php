<?php

namespace Symbiose\Framework\Application;

interface ApplicationInterface
{
	const ENV_DEVELOPMENT	= 1;
	const ENV_TESTING		= 2;
	const ENV_PRODUCTION	= 3;
	
	public function run();
	public function stop();
}