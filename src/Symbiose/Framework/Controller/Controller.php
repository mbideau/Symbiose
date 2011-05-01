<?php

namespace Symbiose\Framework\Controller;

use Symfony\Component\HttpFoundation\Request,
	Symbiose\Component\Service\ServiceContainerAware
;

class Controller
	extends ServiceContainerAware
{
	protected $request;
	protected $loggerService;
	
	function __construct(Request $request)
	{
		$this->request = $request;
	}
	
	public function createResponse($content = '', $status = 200, array $headers = array())
	{
		! $this->getLoggerService() ?: $this->getLoggerService()->info('Controller: creating response : status: ' . $status . ', content: ' . $content); 
		$response = $this->serviceContainer->get('response');
		$response->setContent($content);
		$response->setStatusCode($status);
		foreach ($headers as $name => $value) {
		    $response->headers->set($name, $value);
		}
		return $response;
	}

	/**
	 * Sends an HTTP redirect response
	 */
	public function redirect($url, $status = 302)
	{
		! $this->getLoggerService() ?: $this->getLoggerService()->info('Controller: redirecting (status: ' . $status . ') to ' . $url);
		$response = $this->serviceContainer->get('response');
		$response->setStatusCode($status);
		$response->headers->set('Location', $url);
		return $response;
	}

	public function render($content = '')
	{
		return $this->createResponse($content);
	}
	
	protected function getUrl(array $parameters = array(), $absolute = false)
	{
		$baseUrl = $this->request->getBasePath();
		if($absolute) {
			$baseUrl = $this->request->getHttpHost() . $baseUrl;
		}
		
		$tokens = array();
		
		$components = array_intersect_key(
			$parameters,
			array_flip(
				array(
					'module',
					'controller',
					'action'
				)
			)
		);
		
		// root
		if(empty($components)) {
			return $baseUrl . '/';
		}
		// action
		if(array_key_exists('action', $parameters)) {
			$tokens['action'] = $parameters['action'];
			if(!array_key_exists('controller', $parameters)) {
				$tokens['controller'] = $this->request->get('controller');
			}
			else {
				$tokens['controller'] = $parameters['controller'];
			}
			if(!array_key_exists('module', $parameters)) {
				$tokens['module'] = $this->request->get('module');
			}
			else {
				$tokens['module'] = $parameters['module'];
			}
		}
		// controller
		else if(array_key_exists('controller', $parameters)) {
			$tokens['controller'] = $parameters['controller'];
			if(!array_key_exists('module', $parameters)) {
				$tokens['module'] = $this->request->get('module');
			}
			else {
				$tokens['module'] = $parameters['module'];
			}
		}
		// module
		else if(array_key_exists('module', $parameters)) {
			$tokens['module'] = $parameters['module'];    		
		}
		// format
		if(array_key_exists('format', $parameters)) {
			$format = '.' . $parameters['format'];    		
		}
		krsort($tokens);
		return $baseUrl . '/' . implode('/', $tokens) . (isset($format) ? $format : '/');
	}
}