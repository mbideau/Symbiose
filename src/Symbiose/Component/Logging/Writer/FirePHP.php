<?php

namespace Symbiose\Component\Logging\Writer;

use Zend\Log\Writer\Firebug as BaseWriter,
	FirePHP as Logger
;

class FirePHP
	extends BaseWriter
{
	protected $inspector;
	protected $controller;
	protected $triggerInspect = true;
	protected $consoles;
	protected $groups;
	protected $defaultConsole;
	protected $defaultConsoleName = 'Application';
	protected $defaultGroup;
	protected $defaultGroupName = 'Log';
	protected $defaultGroupTitle = 'Default log messsages';
	
	protected function _init()
	{
		//$this->inspector = Logger::to('request');
		$this->inspector = Logger::to('page');
		//$this->defaultConsole = $this->inspector->console($this->defaultConsoleName);
		$this->defaultConsole = $this->inspector->console();
		$this->defaultGroup = $this
			->defaultConsole
			->group(
				$this->defaultGroupName, $this->defaultGroupTitle
			)
			->open()
		;
		$this->consoles = array($this->defaultConsoleName => $this->defaultConsole);
	}
	
	protected function _log($message, $type = 'log', $console = 'Application', $group = 'Log')
	{
		if($console && array_key_exists($console, $this->consoles)) {
			$console = $this->consoles[$console];
		}
		else {
			$console = $this->defaultConsole;
		}
		if($group) {
			if(!$console->group($group, $group)) {
				$console->group($group, $group)->open();
			}
			$group = $console->group($group);
			$group->$type($message);
		}
		else {
			$console->$type($message);
		}
	}
	
	public function __destruct()
	{
		try {
			if(!$this->controller) {
				try  {
					$this->controller = Logger::to('controller');
				}
				catch(\Exception $e) {
					
				}
			}
			if($this->triggerInspect && $this->controller) {
				//$this->controller->triggerInspect();
			}
		}
		catch(\Exception $exception) {
			error_log(sprintf(
				"FirePHP::%s : Uncaught PHP Exception %s: '%s' at %s line %s\nTrace: %s",
				__FUNCTION__,
				get_class($exception),
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine(),
				$exception->getTraceAsString()
			));
		}
	}
	
	/**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        // enable firephp
        $firephp = Logger::getInstance(true);
		if(!$firephp->getEnabled()) {
			$firephp->setEnabled(true);
		}
        $this->_init();
    }
    
	/**
     * Log a message to the Firebug Console.
     *
     * @param array $event The event data
     * @return void
     */
    protected function _write($event)
    {
        if (!$this->getEnabled()) {
            return;
        }

        if (array_key_exists($event['priority'],$this->_priorityStyles)) {
            $type = $this->_priorityStyles[$event['priority']];
        } else {
            $type = $this->_defaultPriorityStyle;
        }

        $message = $this->_formatter->format($event);

        // build group @todo fix
        /*if(strpos($message, ':') !== false) {
        	$tokens = explode(':', $message);
        	if(count($tokens) > 0) {
        		$group = preg_replace('#[^A-Za-z0-9_.]#', '', trim($tokens[0]));
        	}
        }*/
        
       // $label = isset($event['firebugLabel'])?$event['firebugLabel']:null;

        if(isset($group) && !empty($group)) {
        	$this->_log($message, $type, null, $group);
        }
        else {
        	$this->_log($message, $type);
        }
    }
}