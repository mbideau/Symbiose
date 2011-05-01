<?php

namespace Symbiose\Framework;

use Symbiose\Component\Service\ServiceContainer,
	Symfony\Bundle\FrameworkBundle\EventDispatcher,
	Symfony\Component\EventDispatcher\Event,
	Symfony\Component\HttpFoundation\Request,
	Symfony\Component\HttpFoundation\Response,
	Symbiose\Component\Service\ServiceContainerAware
;

class RenderingListener
	extends ServiceContainerAware
{
	protected $renderingEngineService;
	protected $responseService;
	protected $defaultViewDir;
	protected $moduleDirectory;
	protected $viewModulDir;
	protected $layoutDir;
	protected $defaultLayoutName;
	protected $defaultFormat;
	
	public function __construct(
		$defaultViewDir,
		$moduleDirectory,
		$viewModuleDir,
		$layoutDir,
		$defaultLayoutName,
		$defaultFormat
	)
	{
		$this->defaultViewDir = $defaultViewDir;
		$this->moduleDirectory = $moduleDirectory;
		$this->viewModuleDir = $viewModuleDir;
		$this->layoutDir = $layoutDir;
		$this->defaultLayoutName = $defaultLayoutName;
		$this->defaultFormat = $defaultFormat;
	}

	/**
     * Registers a core.view listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        //$dispatcher->connect('core.view', array($this, 'resolve'), $priority);
    }
	
    /**
     * 
     * @param $event
     */
    public function resolve(Event $event, $arguments)
    {
    	if($arguments instanceof Response) {
    		$response = $arguments;
    	}
    	else {
    		$response = $this->getResponseService();
    	}
    	
        if(is_array($arguments) || is_null($arguments)) {
    		$arguments = is_null($arguments) ? array() : $arguments;
    		$request = $event->getParameter('request');
        
    		// content that will be returned as the response body
	        $content = array_key_exists('content', $arguments) ? $arguments['content'] : '';
	        
	        // do we need to put the content in a view or a layout
	        $useView = array_key_exists('useView', $arguments) ? $arguments['useView'] : false;
	        $useLayout = array_key_exists('useLayout', $arguments) ? $arguments['useLayout'] : false;
	        
	        // use the view or the layout
	        if($useView || $useLayout) {
	        	// parameters to pass to the view and layout
    			$parameters = array_key_exists('parameters', $arguments) ? $arguments['parameters'] : array();
	        
    			$viewPath = array_key_exists('viewPath', $arguments) ? $arguments['viewPath'] : null;
		        $layoutPath = array_key_exists('layoutPath', $arguments) ? $arguments['layoutPath'] : null;
		        // if we need to use a view or a layout and doesn't get the path to it
				if(($useView && empty($viewPath)) || ($useLayout && empty($layoutPath))) {
					// get it from request elements
					$module		= $request->attributes->get('module');
					$format		= $request->attributes->get('format');
					if($useView) {
						$controller	= $request->attributes->get('controller');
						$action		= $request->attributes->get('action');
						$viewName = array_key_exists('viewName', $arguments) ? $arguments['viewName'] : null;
						$viewPath = $this->findViewScript($module, $controller, $action, $format, $viewName);
					}
					if($useLayout) {
						$layoutName = array_key_exists('layoutName', $arguments) ? $arguments['layoutName'] : null;
						$layoutPath = $this->findLayoutScript($module, $format, $layoutName);
					}
				}
				if($useView) {
					$content = $this->getRenderingEngineService()->render($viewPath, $parameters, $content);
				}
				if($useLayout) {
					$content = $this->getRenderingEngineService()->render($layoutPath, $parameters, $content);
				}
	        }
	        $response->setContent($content);
        }
        return $response;
    }
    
	protected function findLayoutScript($module, $format, $layoutName)
	{
		if(empty($module)) {
			throw new Exception("RenderingListener::findLayoutScript : empty module");
		}
		$format = !empty($format) && is_string($format) ? strtolower($format) : $this->defaultFormat;
		$format = $format == 'html' ? 'phtml' : $format;
		$layoutName = !empty($layoutName) && is_string($layoutName) ? strtolower($layoutName) : $this->defaultLayoutName;
		// build the layout file path
		$layoutFile = $this->moduleDirectory . '/' . $module . '/' . $this->layoutDir . '/' . $layoutName . ".$format";
		return $layoutFile;
	}

	protected function findViewScript($module, $controller, $action, $format, $viewName)
	{
		if(empty($module)) {
			throw new Exception("RenderingListener::findViewScript : empty module");
		}
		if(empty($controller)) {
			throw new Exception("RenderingListener::findViewScript : empty controller");
		}
		if(empty($action)) {
			throw new Exception("RenderingListener::findViewScript : empty action");
		}
		$format = !empty($format) && is_string($format) ? strtolower($format) : $this->defaultFormat;
		$format = $format == 'html' ? 'phtml' : $format;
		$viewName = !empty($viewName) && is_string($viewName) ? strtolower($viewName) : $action;
		// build the view file path
		$viewFile = $this->moduleDirectory . '/' . $module . '/' . $this->viewModuleDir . '/' . $controller . '/' . $viewName . ".$format";
		return $viewFile;
	}
}