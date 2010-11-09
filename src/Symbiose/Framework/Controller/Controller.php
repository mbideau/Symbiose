<?php

namespace Falcon\Site\Framework\Controller;

use Symfony\Component\HttpFoundation\Request,
	Falcon\Site\Component\Service\ServiceContainerAware
;

class Controller
	extends ServiceContainerAware
{
	protected $sessionService;
	protected $request;
	protected $parameters = array();

	function __construct(Request $request)
	{
		$this->request = $request;
		$this->parameters = $this->request->attributes->all();
	}
    
	protected function getParameters()
	{
		return $this->parameters;
	}
	
	protected function addParameter($key, $value, $overwrite = true)
	{
		if(!array_key_exists($key, $this->parameters) || $overwrite) {
			$this->parameters[$key] = $value;
		}
	}
	
	protected function getParameter($key, $default = null)
	{
		if(array_key_exists($key, $this->parameters)) {
			return $this->parameters[$key];
		}
		return $default;
	}

	public function createResponse($content = '', $status = 200, array $headers = array())
	{
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
		$response = $this->serviceContainer->get('response');
		$response->setStatusCode($status);
		$response->headers->set('Location', $url);
		return $response;
	}

	/**
	 * Renders a view.
	 *
	 * @param string   $view       The view name
	 * @param array    $parameters An array of parameters to pass to the view
	 * @param Response $response   A response instance
	 *
	 * @return Response A Response instance
	 */
	public function render($layout = null, $useLayout = null)
	{
		$parameters = array_merge(
			$this->getParameters(),
			array('flash_messages' => (array) $this->getSessionService()->getFlashMessages())
		);
		if($useLayout === null) {
			//$useLayout = !$this->request->isXmlHttpRequest();
			$useLayout = (!array_key_exists('format', $parameters) || $parameters['format'] == 'html');
		}
		/*$response = $this->serviceContainer->get('response');
		$response->setContent(
			$this->serviceContainer
				->get('rendering_engine')->render($parameters, $layout, $useLayout)
		);
		return $response;
		*/
		return array(
			'useView'	=> true,
			'useLayout' => $useLayout,
			'layoutName' => $layout,
			'parameters' => $parameters
		);
	}
	
	protected function getUrl(array $parameters = array())
	{
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
			return '/';
		}
		// action
		if(array_key_exists('action', $parameters)) {
			$tokens['action'] = $parameters['action'];
			if(!array_key_exists('controller', $parameters)) {
				$tokens['controller'] = $this->getParameter('controller');
			}
			else {
				$tokens['controller'] = $parameters['controller'];
			}
			if(!array_key_exists('module', $parameters)) {
				$tokens['module'] = $this->getParameter('module');
			}
			else {
				$tokens['module'] = $parameters['module'];
			}
		}
		// controller
		else if(array_key_exists('controller', $parameters)) {
			$tokens['controller'] = $parameters['controller'];
			if(!array_key_exists('module', $parameters)) {
				$tokens['module'] = $this->getParameter('module');
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
		return '/' . implode('/', $tokens) . (isset($format) ? $format : '/');
	}
	
	protected function resetSession()
	{
		$this->getSessionService()->setAttributes(array(
			'_flash'   => array(),
			'_locale'  => $this->getSessionService()->get('_locale', ''),
		));
	}
}