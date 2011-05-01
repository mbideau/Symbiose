<?php

namespace Modules\SimpleWebsite\Controllers;

use Symbiose\Framework\Controller\Controller;

class HelloController
	extends Controller
{
	public function indexAction($name = null)
	{
		return $this->createResponse("Hello $name");
	}
	
	public function withTemplateAction($name = null)
	{
		$templateManager = $this->serviceContainer->get('template_manager');
		$template = $templateManager->loadTemplate('index.phtml');
		return $this->createResponse($template);
		//return $this->createResponse($template->render(array('name' => $name)));
	}
}