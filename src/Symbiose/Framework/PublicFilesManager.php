<?php

namespace Symbiose\Framework;

use Symfony\Component\HttpFoundation\File\File;

class PublicFilesManager
{
	protected $staticRoot;
	
	protected $staticHost = '';
	
	protected $addUploadDir = true;
	
	protected $defaultPublicDir = 'public';
	
	protected $uploadDirname = 'uploads';
	
	protected $extensionGroupDir = array(
		'img'		=> array('jpg', 'jpeg', 'png', 'gif', 'tiff'),
		'docs'		=> array('xls', 'csv')
	);
	
	protected function getGroupDir($extension)
	{
		foreach($this->extensionGroupDir as $dir => $group) {
			if(in_array($extension, $group)) {
				return $dir;
			}
		}
		return $extension;
	}
	
	public function setDefaultPublicDir($dir)
	{
		if(empty($dir)) {
			throw new Exception("PublicFilesManager::setDefaultPublicDir : dir is empty");
		}
		$this->defaultPublicDir = $dir;
	}
	
	public function getDefaultPublicDir()
	{
		return $this->defaultPublicDir;
	}
	
	public function setUploadDirname($name)
	{
		if(empty($name)) {
			throw new Exception("PublicFilesManager::setUploadDirname : name is empty");
		}
		$this->uploadDirname = $name;
	}
	
	public function getUploadDirname()
	{
		return $this->uploadDirname;
	}
	
	public function setAddUploadDir($bool)
	{
		if($bool !== false && empty($bool)) {
			throw new Exception("PublicFilesManager::setAddUploadDir : parameter is empty");
		}
		$this->addUploadDir = (bool) $bool;
	}
	
	public function getAddUploadDir()
	{
		return $this->addUploadDir;
	}
	
	public function setExtensionGroupDir(array $extensionGroupDir = array())
	{
		if(empty($extensionGroupDir)) {
			throw new Exception("PublicFilesManager::setExtensionGroupDir : array is empty");
		}
		$this->extensionGroupDir = $extensionGroupDir;
	}
	
	public function getExtensionGroupDir()
	{
		return $this->extensionGroupDir;
	}
	
	public function setStaticHost($host)
	{
		if(empty($host)) {
			throw new Exception("PublicFilesManager::setStaticHost : host is empty");
		}
		$this->staticHost = $host;
	}
	
	public function getStaticHost()
	{
		return $this->staticHost;
	}
	
	public function setStaticRoot($root)
	{
		if(empty($root)) {
			throw new Exception("PublicFilesManager::setStaticRoot : root dir is empty");
		}
		$root = realpath($root);
		if(!is_dir($root)) {
			throw new Exception("PublicFilesManager::setStaticRoot : root dir '$staticRoot' doesn't exist");
		}
		$this->staticRoot = $root;
	}
	
	public function getStaticRoot()
	{
		return $this->staticRoot;
	}
	
	public function moveFile($fromPath, $toFilename = null, $toModule = null)
	{
		if(empty($fromPath)) {
			throw new Exception("PublicFilesManager::moveFile : file is empty");
		}
		if(!file_exists($fromPath)) {
			throw new Exception("PublicFilesManager::moveFile : file '$fromPath' doesn't exist");
		}
		$parentDir = $this->getParentDir($fromPath, $toModule);
		if(!is_dir($parentDir)) {
			if(!@mkdir($parentDir, 0775, true)) {
				throw new Exception("PublicFilesManager::moveFile : failed to create parent dir '$parentDir'");
			}
		}
		$toFilename = !empty($toFilename) ? $toFilename : basename($fromPath);
		$toDestPath = $parentDir . '/' . $toFilename;
		if(!@rename($fromPath, $toDestPath)) {
			throw new Exception("PublicFilesManager::moveFile : failed to move file '$fromPath' to '$toDestPath'");
		}
		return $toDestPath;
	}
	
	public function moveUploadedFile($fromPath, $toFilename, $toModule = null)
	{
		if(empty($fromPath)) {
			throw new Exception("PublicFilesManager::moveUploadedFile : file is empty");
		}
		if(empty($toFilename)) {
			throw new Exception("PublicFilesManager::moveUploadedFile : filename is empty");
		}
		if(!file_exists($fromPath)) {
			throw new Exception("PublicFilesManager::moveUploadedFile : file '$fromPath' doesn't exist");
		}
		$parentDir = $this->getParentDir($fromPath, $toModule, true);
		if(!is_dir($parentDir)) {
			if(!@mkdir($parentDir, 0775, true)) {
				throw new Exception("PublicFilesManager::moveUploadedFile : failed to create parent dir '$parentDir'");
			}
		}
		$toDestPath = $parentDir . '/' . $toFilename;
		if(!@rename($fromPath, $toDestPath)) {
			throw new Exception("PublicFilesManager::moveUploadedFile : failed to move file '$fromPath' to '$toDestPath'");
		}
		return $toDestPath;
	}
	
	public function hasFile($filename, array $parameters = array())
	{
		if(empty($filename)) {
			throw new Exception("You must provide a filename");
		}
		$basedir = array_key_exists('basedir', $parameters) ? $parameters['basedir'] : null;
		$parentDir = $this->staticRoot . ($basedir ? $basedir : '');
		$filePath = $parentDir . '/' . $filename;
		return file_exists($filePath);
	}
	
	public function getFilePath($filename, array $parameters = array())
	{
		if(empty($filename)) {
			throw new Exception("You must provide a filename");
		}
		$basedir = array_key_exists('basedir', $parameters) ? $parameters['basedir'] : null;
		$parentDir = $this->staticRoot . ($basedir ? $basedir : '');
		$filePath = $parentDir . '/' . $filename;
		return $filePath;
	}
	
	public function copyFile($src, $filename = null, array $parameters = array())
	{
		if(empty($src)) {
			throw new Exception("You must provide a source file");
		}
		if(!file_exists($src)) {
			throw new Exception("Source file $src doesn't exist");
		}
		if(empty($filename)) {
			$filename = basename($src);
		}
		$basedir = array_key_exists('basedir', $parameters) ? $parameters['basedir'] : null;
		$parentDir = $this->staticRoot . ($basedir ? $basedir : '');
		$filePath = $parentDir . '/' . $filename;
		return @copy($src, $filePath);
	}
	
	public function getUrl($filename, array $parameters = array())
	{
		if(empty($filename)) {
			throw new Exception("You must provide a filename");
		}
		$domain = array_key_exists('domain', $parameters) ? $parameters['domain'] : null;
		$basedir = array_key_exists('basedir', $parameters) ? $parameters['basedir'] : null;
		$host = $domain ? "http://cdn.$domain" : $this->staticHost;
		$parentDir = $host . ($basedir ? $basedir : '');
		$fileUrl = $parentDir . '/' . $filename;
		return $fileUrl;
	}
	
	protected function getParentDir($file, $module = null, $isUploaded = false)
	{
		$parentDir = '';
		if(file_exists($file)) {
			$fileObject = new File($file);
			$extension = $fileObject->getDefaultExtension();
		}
		else {
			$extension = $this->getExtension(basename($file));
		}
		$extension = preg_replace('#^\.#', '', $extension);
		if(empty($extension)) {
			throw new Exception("PublicFilesManager::getParentDir : can't get file extension '$file'");
		}
		return $this->getExtensionDir($extension, $module, $isUploaded);
	}
	
	public function getExtensionDir($extension, $module = null, $isUploaded = false)
	{
		$parentDir = '';
		if(empty($extension)) {
			throw new Exception("PublicFilesManager::getExtensionDir : extension is empty");
		}
		$extensionGroupDir = $this->getGroupDir($extension);
		if(!empty($module)) {
			if($isUploaded && $this->addUploadDir) {
				$parentDir = $module . '/' . $extensionGroupDir . '/' . $this->uploadDirname;
			}
			else {
				$parentDir = $module . '/' . $extensionGroupDir;
			}
		}
		else {
			if($isUploaded && $this->addUploadDir) {
				$parentDir = $this->defaultPublicDir . '/' . $extensionGroupDir . '/' . $this->uploadDirname;
			}
			else {
				$parentDir = $this->defaultPublicDir . '/' . $extensionGroupDir;
			}
		}
		return $this->staticRoot . '/' . $parentDir;
	}
	
	public function getExtensionDirUrl($extension, $module = null, $isUploaded = false)
	{
		$dir = str_replace(
			$this->staticRoot . '/',
			'',
			$this->getExtensionDir($extension, $module, $isUploaded)
		);
		return $this->staticHost . '/' . $dir;
	}
	
	/**
     * Returns the file extension (with dot)
     *
     * @return string
     */
    public function getExtension($filename)
    {
        if (false !== ($pos = strrpos($filename, '.'))) {
            return substr($filename, $pos);
        } else {
            return '';
        }
    }
}