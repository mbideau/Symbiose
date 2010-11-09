<?php

namespace Falcon\Site\Component\Caching;

use Falcon\Site\Component\Caching\Exception\CachingException as Exception;

class FileCache
{
	const DIR_MODE = '0770';
	const FILE_MODE = '0774';
	
	protected static $defaultCacheDir = '../datas/cache';
	
	protected $dir;
	protected $filename;
	
	public function __construct($filename = null, $dir = null)
	{
		$this->filename = $filename;
		$this->dir = empty($dir) ? self::$defaultCacheDir : $dir;
	}
	
	static public function setDefaultCacheDir($dir, $create = false)
	{
		if(empty($dir)) {
			throw new Exception("Default cache dir can't be empty");
		}
		elseif(!is_dir($dir)) {
			if(!$create) {
				throw new Exception("Default cache dir '$dir' doesn't exist");
			}
			else {
				if(!mkdir($dir, octdec(self::DIR_MODE), true)) {
					throw new Exception("Failed to create default cache dir '$dir'");
				}
			}
		}
		if(!is_writable($dir)) {
			throw new Exception("Default cache dir '$dir' is not writable");
		}
		self::$defaultCacheDir = $dir;
	}
	
	public function writeCacheFile($content, $append = false, $writeControl = true, $filename = null)
	{
		$filename = empty($filename) ? $this->filename : $filename;
		if(empty($filename)) {
			throw new Exception("Filename can't be empty");
		}
		$dir = $this->dir;
		if(empty($dir)) {
			throw new Exception("Cache dir can't be empty");
		}
		if(!is_dir($dir)) {
			if(!$create) {
				throw new Exception("Cache dir '$dir' doesn't exist");
			}
			else {
				if(!mkdir($dir, octdec(self::DIR_MODE), true)) {
					throw new Exception("Failed to create cache dir '$dir'");
				}
			}
		}
		if(!is_writable($dir)) {
			throw new Exception("Cache dir '$dir' is not writable");
		}
		$file = "$dir/$filename";
		$needChmod = file_exists($file) && $append;
		
		if($append) {
			if(!@file_put_contents($file, $content, FILE_APPEND) !== false) {
	        	throw new Exception("Failed to write cache file '$file'");
	        }
		}
		else {
			$tmpFile = tempnam($this->dir, $this->filename);
	        if(
	        	!@file_put_contents($tmpFile, $content) !== false
	        	|| !@rename($tmpFile, $file)
	        ) {
	        	throw new Exception("Failed to write cache file '$file'");
	        }
			if($writeControl) {
				if($content != $this->getCacheFileContent($filename)) {
					throw new Exception("Failed to write cache file '$file' (corrupted)");
				}
			}
		}
		if($needChmod && !@chmod($file, octdec(self::FILE_MODE))) {
			throw new Exception("Failed to change mode of cache file '$file'");
		}
	}
	
	public function hasCacheFile($filename = null)
	{
		return is_readable($this->getCacheFilePath($filename));
	}
	
	public function getCacheFilePath($filename = null)
	{
		$filename = empty($filename) ? $this->filename : $filename;
		if(empty($filename)) {
			throw new Exception("Filename can't be empty");
		}
		$dir = $this->dir;
		if(empty($dir)) {
			throw new Exception("Cache dir can't be empty");
		}
		$file = "$dir/$filename";
		return $file;
	}
	
	public function getCacheFileContent($filename = null)
	{
		$file = $this->getCacheFilePath($filename);
		if(empty($file)) {
			throw new Exception("Failed to get cache file path(empty)");
		}
		if(($content = @file_get_contents($file)) === false) {
			throw new Exception("Failed to get cache file content ('$file')");
		}
		return $content;
	}
}

