<?php

namespace Falcon\Site\Component\Logging;

//require_once 'Zend/Log.php';
use Symfony\Component\HttpKernel\Log\LoggerInterface;

class Logger
	extends \Zend_Log
	implements LoggerInterface
{
	protected static $instance = null;
	
	public static function getInstance()
	{
		return self::$instance;
	}
	
	public function __construct($writer = null)
    {
    	if(empty(self::$instance)) {
    		self::$instance = $this;
    	}
    	parent::__construct($writer);
    }
	
    public function emerg($message)
    {
        return parent::log($message, 0);
    }

    public function alert($message)
    {
        return parent::log($message, 1);
    }

    public function crit($message)
    {
        return parent::log($message, 2);
    }

    public function err($message)
    {
        return parent::log($message, 3);
    }

    public function warn($message)
    {
        return parent::log($message, 4);
    }

    public function notice($message)
    {
        return parent::log($message, 5);
    }

    public function info($message)
    {
        return parent::log($message, 6);
    }

    public function debug($message)
    {
        return parent::log($message, 7);
    }
}
