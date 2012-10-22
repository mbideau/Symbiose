<?php

namespace Symbiose\Framework\Application;

use Symbiose\Framework\Application\ApplicationBaseStateful,
    RuntimeException as Exception,
    Zend\Log\Logger,
	Zend\Log\Writer\Stream,
	Zend\Log\Filter\Priority,
	Zend\Log\Formatter\Simple as Formatter
;

/**
 * A CLI application statefull
 * @author Michael Bideau
 */
class CliApplicationBaseStateful
	extends ApplicationBaseStateful
{
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBase#detectSiteRoot()
	 */
	protected function detectSiteRoot()
	{
		return dirname($_SERVER['PWD']);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBase#detectDomain()
	 */
	protected function detectDomain()
	{
		/*$host = null;
		if(isset($_SERVER['argv'][1])) {
			$host = $_SERVER['argv'][1];
			$_SERVER['argv'][1] = $_SERVER['argv'][0];
			array_shift($_SERVER['argv']);
		}
		//echo "argv : \n" . print_r($argv, true); die();
		$domain = null;
		if(!empty($host) && is_string($host)) {
			$hostTokens = explode('.', $host);
			if(count($hostTokens) > 2) {
				$domain = $hostTokens[count($hostTokens) - 2] . '.' . $hostTokens[count($hostTokens) - 1];
			}
			else {
				$this->domain = $host;
			}
		}
		if(empty($this->domain)) {
			throw new Exception("you must specify a domain as the first argument");
		}*/
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBase#initLogger()
	 */
	protected function initLogger()
	{
		$this->logger = new Logger();
		// log errors
		$steeamWriter = new Stream($this->logPath . '/cli-error.log');
		$filterError = new Priority(4);
		$steeamWriter->addFilter($filterError);
		$formatter = new Formatter("%timestamp% %priorityName%: %message%\n");
		$steeamWriter->setFormatter($formatter);
		$this->logger->addWriter($steeamWriter);
		return $this;
	}
	
	/**
	 * Do nothing during destruction in CLI mode
	 * (non-PHPdoc)
	 * @see library/Symbiose/src/Symbiose/Framework/Application/Symbiose\Framework\Application.ApplicationBaseStateful#__destruct()
	 */
	public function __destruct()
	{
		! $this->logger ?: $this->logger->debug('Application: Application ending ...');
	}
}
