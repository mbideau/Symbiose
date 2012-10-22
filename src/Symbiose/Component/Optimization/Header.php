<?php

namespace Symbiose\Component\Optimization;

use Symbiose\Component\Service\ServiceContainerAware,
	RuntimeException as Exception,
	DOMDocument
;

class Header
	extends ServiceContainerAware
{
	protected $combinatorService;
	protected $minifierService;
	protected $publicFilesManagerService;
	
	public function optimize($content)
	{
		$optimized = $content;
		$hasChanged = false;
		// get dom document
		$dom = $this->getDomDocument($optimized, true);
		// get head
		$headNodes = $this->getTextBetweenTags($dom, 'head', array(), false);
		if(!empty($headNodes)) {
			$headNode = reset($headNodes);
			$optimizedHeadNode = $headNode->cloneNode(true);
			// optimize css
			if($this->optimizeCss($optimizedHeadNode)) {
				$hasChanged = true;
			}
			/*echo '<br/><strong>Optimization CSS</strong><br/><pre>' . htmlentities(str_replace("<", "\n<", $optimizedHeadNode->ownerDocument->saveXML($optimizedHeadNode))) . '</pre>';*/
			// optimize js
			if($this->optimizeJs($optimizedHeadNode)) {
				$hasChanged = true;
			}
			/*echo '<br/><strong>Optimization JS</strong><br/><pre>' . htmlentities(str_replace("<", "\n<", $optimizedHeadNode->ownerDocument->saveXML($optimizedHeadNode))) . '</pre>'; die();*/
		}
		if($hasChanged && $headNode && $optimizedHeadNode) {
			/*echo '<br/><strong>Avant Optimization</strong><br/><pre>' . htmlentities(str_replace("<", "\n<", $headNode->ownerDocument->saveXML($headNode))) . '</pre>';
			echo '<br/><strong>Après Optimization</strong><br/><pre>' . htmlentities(str_replace("<", "\n<", $optimizedHeadNode->ownerDocument->saveXML($optimizedHeadNode))) . '</pre>';
			die();*/
			// replace the head node
			$htmlNodes = $this->getTextBetweenTags($dom, 'html');
			if(!empty($htmlNodes)) {
				$htmlNode = reset($htmlNodes);
				if(!empty($htmlNode)) {
					$htmlNode->replaceChild($optimizedHeadNode, $headNode);
					// dump the content from dom
					$content = $dom->saveHTML();
				}
			}
			/*echo "<pre>" . htmlentities($content) . "</pre>"; die();*/
		}
		return $content;
	}
	
	protected function optimizeCss($headNode)
	{
		// get css dom nodes
		$domNodes = $this->getTextBetweenTags($headNode, 'link', array(
			'rel' => 'stylesheet',
			'type' => 'text/css',
			'href' => true
		), false);
		return $this->getOptimizedHead('.css', $headNode, $domNodes, 'href');
	}
	
	protected function optimizeJs($headNode)
	{
		// get js dom nodes
		$domNodes = $this->getTextBetweenTags($headNode, 'script', array(
			'type' => 'text/javascript',
			'src' => true
		), false);
		return $this->getOptimizedHead('.js', $headNode, $domNodes, 'src');
	}
	
	protected function getOptimizedHead($fileSuffix, $headNode, $domNodes, $fileAttribute)
	{
		$optimized = $headNode;
		// if there are css dom nodes
		if(!empty($domNodes)) {
			// get css files (or url)
			foreach($domNodes as $index => $node) {
				$files[$index] = $node->attributes->getNamedItem($fileAttribute)->nodeValue;
			}
			// build css cache key for this set of files
			$cacheKey = md5(implode("\n", $files));
			// combined css file is not cached
			if(!$this->getCombinatorService()->isCached($cacheKey)) {
				// build a list of minified content (to be combined)
				$filesContents = array();
				foreach($files as $index => $file) {
					//$uid = preg_replace('#[^a-zA-Z0-9._-]#', '-', $file);
					$uid = $file;
					// if the file is not already minified
					if(stripos($file, '.min') === false && stripos($file, '-min') === false) {
						/*var_dump(array('minifing file' => $file));*/
						$minifiedContent = $this->getMinifierService()->minify($file);
					}
					else {
						$minifiedContent = file_get_contents($file);
					}
					// add it to the list
					$filesContents[$uid] = $minifiedContent;
				}
				// combine them into one content
				$this->getCombinatorService()->combine($filesContents, $cacheKey);
			}
			// get combined css file
			$combined = $this->getCombinatorService()->getCache($cacheKey, true);
			// get filename
			$filename = basename($combined) . $fileSuffix;
			// build public files parameters
			$pbfParams = array(
				'basedir' => '/cache'
			);
			// if the public version not exists
			if(!$this->getPublicFilesManagerService()->hasFile($filename, $pbfParams)) {
				// copy the combined file to the public dir
				$this->getPublicFilesManagerService()->copyFile($combined, $filename, $pbfParams);
			}
			// get the public url
			$publicUrl = $this->getPublicFilesManagerService()->getUrl($filename, $pbfParams);
			// the first link become the only one
			$firstNode = reset($domNodes);
			$newFirstNode = $firstNode->cloneNode(true);
			$newFirstNode->setAttribute($fileAttribute, $publicUrl);
			$optimized->replaceChild($newFirstNode, $firstNode);
			array_shift($domNodes);
			if(!empty($domNodes)) {
				foreach($domNodes as $index => $node) {
					$optimized->removeChild($node);
				}
			}
			return true;
		}
		return false;
	}
	
	/**
	 * Get a DOMDocument from an html content
	 * @param string $content The html content
	 * @param boolean $strict Whether to use strict mode
	 */
	protected function getDomDocument($html, $strict = false)
	{
		// a new dom object
		$dom = new DOMDocument();
		// load the html into the object
		if($strict) {
			$dom->loadXML($html);
		}
		else {
			/*
			$dump = htmlentities($html);
			$dumpTokens = explode("\n", $dump);
			echo '<pre>';
			$line = 0;
			foreach($dumpTokens as $d) {
				echo ++$line . "\t" . $d . "\n";
			}
			echo '</pre>'; die();
			*/
			$dom->loadHTML($html);
		}
		return $dom;
	}
	
	/**
	 * @get text between tags
	 *
	 * @param DOMDOcument $dom The DOM Document
	 * @param string $tag The tag name
	 * @param array $attributes An optional array to match against attributes keys and values
	 * @return array
	 */
	protected function getTextBetweenTags($dom, $tag, array $attributes = array())
	{
		/*var_dump(array(
			'dom' => $dom, 'tag' => $tag, 'attributes' => $attributes
		));*/
		// discard white space
		$dom->preserveWhiteSpace = false;

		// the tag by its tag name
		$content = $dom->getElementsByTagname($tag);
		
		// do we need to do a node matching
		$doMatching = !empty($attributes);
		/*var_dump(array('$doMatching' => $doMatching));*/
		// the array to return
		$out = array();
		foreach ($content as $item) {
			/*echo '<pre>' . htmlentities($item->ownerDocument->saveXML($item)) . '</pre>';*/
			$match = true;
			if($doMatching) {
				if(empty($item->attributes)) {
					/*echo "no match (empty attributes)\n";*/
					$match = false;
				}
				else {
					foreach($attributes as $key => $value) {
						/*var_dump($item->attributes->getNamedItem($key)->nodeValue);*/
						if(!$item->attributes->getNamedItem($key) || $item->attributes->getNamedItem($key)->nodeValue != $value) {
							/*echo "no match (attribute $key failed with value " . $item->attributes->getNamedItem($key)->nodeValue . ")\n";*/
							$match = false;
						}
					}
				}
			}
			// if the node matches
			if($match) {
				/*var_dump(array('matching node' => $item->nodeValue));*/
				// add node value to the out array
				$out[] = $item;
			}
		}
		// return the results
		return $out;
	}
}