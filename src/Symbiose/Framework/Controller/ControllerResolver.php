<?php

namespace Falcon\Site\Framework\Controller;

use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface,
	Symfony\Component\HttpFoundation\Request,
	Falcon\Site\Framework\Controller\Exception\ControllerException as Exception,
	Falcon\Site\Component\Service\ServiceContainerAware
;

class ControllerResolver
	extends ServiceContainerAware
	implements ControllerResolverInterface
{
	protected $loggerService;
	protected $moduleManagerService;
	protected $moduleDirectory;
	protected $moduleControllerDirectory;

	protected function getControllerClassName($controller)
	{
		return ucfirst(preg_replace_callback(
			'|[-_](\S)|',
			create_function(
				'$matches',
				'return ucfirst($matches[1]);'
			),
			$controller
		)) . 'Controller';
	}
	
	protected function getActionMethodName($action)
	{
		return preg_replace_callback(
			'|[-_](\S)|',
			create_function(
				'$matches',
				'return ucfirst($matches[1]);'
			),
			$action
		) . 'Action';
	}
	
	/**
	 * Returns the Controller instance associated with a Request.
	 *
	 * This method looks for a '_controller' request parameter that represents
	 * the controller name (a string like BlogBundle:Post:index).
	 *
	 * @param \Symfony\Component\HttpFoundation\Request $request A Request instance
	 *
	 * @return mixed|Boolean A PHP callable representing the Controller,
	 *                       or false if this resolver is not able to determine the controller
	 *
	 * @throws \InvalidArgumentException|\LogicException If the controller can't be found
	 */
	public function getController(Request $request)
	{
		// get internal controller
		$internalController = $request->attributes->get('_controller');
		if(!empty($internalController)) {
			if(is_callalbe($internalController)) {
				return $internalController;
			}
		}
		
		// get request parameters
		$module		= $request->attributes->get('module');
		$controller	= $request->attributes->get('controller');
		$action		= $request->attributes->get('action');
		
		// check request parameters
		if(empty($module)) {
			throw new Exception(__FUNCTION__ . " : empty module");
		}
		if(empty($controller)) {
			throw new Exception(__FUNCTION__ . " : empty controller");
		}
		if(empty($action)) {
			throw new Exception(__FUNCTION__ . " : empty action");
		}
		
		// build controller class name
		$controllerClassName = $this->getControllerClassName($controller);
		
		// build the controller class namespace
		$controllerClassNamespace = 'Modules\\' . $this->getModuleManagerService()->getProperModuleName($module) . "\\Controllers\\$controllerClassName";
		
		// if the class doesn't exist (autloding enabled)
		if(!class_exists($controllerClassNamespace)) {
			throw new Exception(sprintf(__FUNCTION__ . " : Unable to find controller '%s' for module '%s'", $controller, $module));
		}
		
		// instantiate the controller
		$controllerClass = new $controllerClassNamespace($request);
		$controllerClass->setServiceContainer($this->serviceContainer);

		// get the method name
		$methodName = $this->getActionMethodName($action);
		// if method doesn't exist
		if (!method_exists($controllerClass, $methodName)) {
			throw new Exception(sprintf('ControllerResolver::getController : Method "%s::%s" does not exist.', $controllerClassName, $methodName));
		}
		
		// log controller usage
		if ($this->getLoggerService()) {
		    $this->getLoggerService()->info(sprintf('Using controller "%s::%s"%s', $controllerClassName, $methodName, isset($controllerPath) ? sprintf(' from file "%s"', $controllerPath) : ''));
		}

		return array($controllerClass, $methodName);
	}

	/**
	 * Returns the arguments to pass to the controller.
	 *
	 * @param \Symfony\Component\HttpFoundation\Request $request    A Request instance
	 * @param mixed                                      $controller A PHP callable
	 *
	 * @throws \RuntimeException When value for argument given is not provided
	 */
	public function getArguments(Request $request, $controller)
	{
		$attributes = $request->attributes->all();

		list($controller, $method) = $controller;

		$r = new \ReflectionObject($controller);
		$arguments = array();
		foreach ($r->getMethod($method)->getParameters() as $param) {
		    if (array_key_exists($param->getName(), $attributes)) {
				$arguments[] = $attributes[$param->getName()];
		    }
		    elseif ($param->isDefaultValueAvailable()) {
				$arguments[] = $param->getDefaultValue();
		    }
		    else {
				throw new \RuntimeException(sprintf('Controller "%s::%s()" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', get_class($controller), $method, $param->getName()));
		    }
		}
		return $arguments;
	}
}