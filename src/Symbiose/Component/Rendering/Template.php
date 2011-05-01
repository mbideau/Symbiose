<?php

use Doctrine\Common\Collection\ArrayCollection;

class HtmlContent
{
	protected $id;
	protected $cssLinks = array();
	protected $cssContent;
	protected $jsLinks = array();
	protected $jsContent;
	protected $html;
	protected $childrenContent;
	protected $parentContent;
	
	public function setValues(array $values)
	{
		if(empty($this->childrenContent)) {
			$this->childrenContent = new ArrayCollection();
		}
		return $this;
	}
	
	public function getContent()
	{
		// if not in cache
			// build content
		// in cache
			// retun content
	}
}