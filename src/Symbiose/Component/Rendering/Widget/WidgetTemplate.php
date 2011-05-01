<?php

namespace Symbiose\Component\Rendering\Widget;

use Symbiose\Component\Rendering\Widget\Widget,
	Twig_Loader_String
;

class WidgetTemplate
	extends Widget
{
	protected $templateManager;
	protected $parameters = array();
	
	public function __construct($templateManager = null, array $parameters = array())
	{
		$this->templateManager = $templateManager;
		$this->parameters = $parameters;
	}
	
	public function render()
	{
		$this->templateManager->setLoader(new Twig_Loader_String());
		$template = $this->templateManager->loadTemplate($this->html);
		return $template->render($this->parameters);
	}
}