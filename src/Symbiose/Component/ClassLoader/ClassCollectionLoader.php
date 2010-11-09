<?php

namespace Falcon\Site\Component\ClassLoader;

class ClassCollectionLoader
{
	static public function load($classes, $cacheDir, $name, $autoReload)
	{
		$cache = $cacheDir.'/'.$name.'.php';

		$reload = false;
		if($autoReload) {
			$metadata = $cacheDir.'/'.$name.'.meta';
			if(!file_exists($metadata) || !file_exists($cache)) {
				$reload = true;
			}
			else {
				$time = filemtime($cache);
				$meta = unserialize(file_get_contents($metadata));

				if($meta[1] != $classes) {
					$reload = true;
				}
				else {
					foreach($meta[0] as $resource) {
						if(!file_exists($resource) || filemtime($resource) > $time) {
							$reload = true;
							break;
						}
					}
				}
			}
		}

		if(!$reload && file_exists($cache)) {
			require_once $cache;
			return;
		}

		$files = array();
		$content = '';
		foreach($classes as $class) {
			if(!class_exists($class) && !interface_exists($class)) {
				throw new \InvalidArgumentException(sprintf('Unable to load class "%s"', $class));
			}

			$r = new \ReflectionClass($class);
			$files[] = $r->getFileName();

			$content .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($r->getFileName()));
		}

		if(!is_dir(dirname($cache))) {
			mkdir(dirname($cache), 0777, true);
		}
		self::writeCacheFile($cache, Kernel::stripComments('<?php '.$content));

		if($autoReload) {
			self::writeCacheFile($metadata, serialize(array($files, $classes)));
		}
	}

	static protected function writeCacheFile($file, $content)
	{
		$tmpFile = tempnam(dirname($file), basename($file));
		if (!$fp = @fopen($tmpFile, 'wb'))
		{
			die(sprintf('Failed to write cache file "%s".', $tmpFile));
		}
		@fwrite($fp, $content);
		@fclose($fp);

		if ($content != file_get_contents($tmpFile))
		{
			die(sprintf('Failed to write cache file "%s" (cache corrupted).', $tmpFile));
		}

		@rename($tmpFile, $file);
		chmod($file, 0644);
	}
