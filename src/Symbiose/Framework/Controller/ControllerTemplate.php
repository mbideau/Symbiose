<?php

namespace Symbiose\Framework\Controller;

use Symfony\Component\HttpFoundation\Request,
	Symbiose\Component\Service\ServiceContainerAware,
	Symbiose\Framework\Controller\Controller,
	Symbiose\Framework\Controller\Exception\ControllerException as Exception,
	Twig_Loader_Filesystem
;

class ControllerTemplate
	extends Controller
{
	protected $templateManagerService;
	protected $moduleManagerService;
	protected $tplParameters = array();

	function __construct(Request $request)
	{
		parent::__construct($request);
		$this->tplParameters = $this->request->attributes->all();
	}
    
	protected function getTplParameters()
	{
		return $this->tplParameters;
	}
	
	protected function addTplParameter($key, $value, $overwrite = true)
	{
		if(!array_key_exists($key, $this->tplParameters) || $overwrite) {
			$this->tplParameters[$key] = $value;
		}
	}
	
	protected function getTplParameter($key, $default = null)
	{
		if(array_key_exists($key, $this->tplParameters)) {
			return $this->tplParameters[$key];
		}
		return $default;
	}
	
	public function render($content = '')
	{
		$templateManager = $this->getTemplateManagerService();
		$template = $this->getTemplate($templateManager);
		return $this->createResponse($template->render($this->getTplParameters()));
	}
	
	protected function getTemplate($templateManager)
	{
		//-- build dir path containing templates
		// get current module path
		$moduleManager = $this->getModuleManagerService();
		$moduleName = $this->getTplParameter('module');
		$module = $moduleManager->get($moduleName);
		if(!$module) {
			throw new Exception("Failed to render. Unable to get module '$moduleName'");
		}
		$modulePath = $module->getPath();
		// dir path is '<module>/views/<controller>'
		$dirpath = $modulePath . DS . 'views' . DS . $this->getTplParameter('controller');
		// set template manager loader to dir path
		$templateManager->setLoader(new Twig_Loader_Filesystem($dirpath));
		
		//-- build template filename
		// filename is '<action>.<format>'
		$filename = $this->getTplParameter('action') . '.' . ($this->getTplParameter('format') == 'html' ? 'phtml' : $this->getTplParameter('format'));
		
		return $templateManager->loadTemplate($filename);
	}
}