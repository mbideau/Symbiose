<?php

namespace Symbiose\Framework;

use Symbiose\Framework\Application,
	Symfony\Component\Console\Application as Console
;

class ApplicationCli
	extends Application
{
	protected $console;
	
	protected $name;
	protected $version;
	
	protected function isCli()
	{
		return !isset($_SERVER['HTTP_HOST']);
	}
	
	public function run($name = 'UNKNOWN', $version = 'UNKNOWN')
	{
		// bootstrap the application
		$this->bootstrap();
		
		$this->console = new Console($name, $version);
		return $this->console->run();
	}
}