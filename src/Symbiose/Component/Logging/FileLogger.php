<?php

namespace Symbiose\Component\Logging;

use Symbiose\Component\Logging\Exception\LoggingException as Exception;

class FileLogger
{
	const DIR_MODE = '0770';
	const FILE_MODE = '0664';
	
	const ERROR = 0;
	const WARN = 1;
	const INFO = 2;
	const DEBUG = 4;
	
	protected static $defaultLogDir = '../data/logs';
	protected static $defaultLogFilename = 'default.log';

	protected $logDir;
	protected $logFilename;
	
	public function __construct($logDir = null, $logFilename = null)
	{
		$this->logDir = $logDir;
		$this->logFilename = $logFilename;
	}
	
	static public function setDefaultLogFilename($filename)
	{
		if(!is_string($filename)) {
			throw new Exception("Defaul log filename must be a string (" . gettype($filename) . " given)");
		}
		self::$defaultLogFilename = $filename;
	}
	
	static public function setDefaultLogDir($dir, $create = false)
	{
		if(empty($dir)) {
			throw new Exception("Default log dir can't be empty");
		}
		elseif(!is_dir($dir)) {
			if(!$create) {
				throw new Exception("Default log dir '$dir' doesn't exist");
			}
			else {
				if(!mkdir($dir, octdec(self::DIR_MODE), true)) {
					throw new Exception("Failed to create default log dir '$dir'");
				}
			}
		}
		if(!is_writable($dir)) {
			throw new Exception("Default log dir '$dir' is not writable");
		}
		self::$defaultLogDir = $dir;
	}
	
	public function log($message, $priority = self::DEBUG, $append = true)
	{
		self::directLog($message, $priority, $append, $this->logFilename, $this->logDir);
	}
	
	static public function directLog($message, $priority = self::DEBUG, $append = true, $filename = null, $dir = null)
	{
		// disable log
		//return;
		
		$filename = empty($filename) ? self::$defaultLogFilename : null;
		if(empty($filename)) {
			throw new Exception("Filename can't be empty");
		}
		$dir = empty($dir) ? self::$defaultLogDir : $dir;
		if(empty($dir)) {
			throw new Exception("Log dir can't be empty");
		}
		if(!is_dir($dir)) {
			if(!$create) {
				throw new Exception("Log dir '$dir' doesn't exist");
			}
			else {
				if(!mkdir($dir, octdec(self::DIR_MODE), true)) {
					throw new Exception("Failed to create log dir '$dir'");
				}
			}
		}
		if(!is_writable($dir)) {
			throw new Exception("Log dir '$dir' is not writable");
		}
		$file = "$dir/$filename";
		$needChmod = !file_exists($file) || !$append;
		
		// add time info at the begining of the message
		$message = '[' . date('c') . ' ' . str_pad(preg_replace(array('#^([0-9]+)$#', '#^([0-9]+\.[0-9])$#'), array('${1}.00', '${1}0'), microtime(true)), 14, ' ', STR_PAD_LEFT) . ']' . " $message\n";
		
		if($append) {
			if(!@file_put_contents($file, $message, FILE_APPEND) !== false) {
	        	throw new Exception("Failed to write log file '$file'");
	        }
		}
		else {
			$tmpFile = tempnam($dir, $filename);
	        if(
	        	!@file_put_contents($tmpFile, $message) !== false
	        	|| !@rename($tmpFile, $file)
	        ) {
	        	throw new Exception("Failed to write log file '$file'");
	        }
		}
		if($needChmod && !@chmod($file, octdec(self::FILE_MODE))) {
			throw new Exception("Failed to change mode of log file '$file'");
		}
	}
}