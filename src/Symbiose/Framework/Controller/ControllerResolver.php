<?php

namespace Symbiose\Framework\Controller;

use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface,
	Symfony\Component\HttpFoundation\Request,
	Symbiose\Framework\Controller\Exception\ControllerException as Exception,
	Symbiose\Component\Service\ServiceContainerAware,
	Symfony\Component\HttpKernel\Exception\NotFoundHttpException
;

class ControllerResolver
	extends ServiceContainerAware
	implements ControllerResolverInterface
{
	protected $loggerService;
	protected $moduleManagerService;

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
	
	public function getProperModuleName($module)
	{
		return ucfirst(preg_replace_callback(
			'|[-_.](\S)|',
			create_function(
				'$matches',
				'return ucfirst($matches[1]);'
			),
			$module
		));
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
			if(is_callable($internalController)) {
				return $internalController;
			}
			elseif(
        		!empty($internalController)
        		&& array_key_exists('module', $internalController)
        		&& array_key_exists('controller', $internalController)
        		&& array_key_exists('action', $internalController)
        	) {
				$module		= $internalController['module'];
				$controller	= $internalController['controller'];
				$action		= $internalController['action'];
			}
		}
		else {
			// get request parameters
			$module		= $request->attributes->get('module');
			$controller	= $request->attributes->get('controller');
			$action		= $request->attributes->get('action');
		}
		
		if($this->getLoggerService()) {
		    $this->getLoggerService()->info(sprintf('Handling request : /%s/%s/%s', $module, $controller, $action));
		}
		
		// check request parameters
		if(empty($module)) {
			throw new NotFoundHttpException(__FUNCTION__ . " : empty module");
		}
		if(empty($controller)) {
			throw new NotFoundHttpException(__FUNCTION__ . " : empty controller");
		}
		if(empty($action)) {
			throw new NotFoundHttpException(__FUNCTION__ . " : empty action");
		}
		
		// build controller class name
		$controllerClassName = $this->getControllerClassName($controller);
		
		// build the controller class namespace
		$controllerClassNamespace = 'Modules\\' . $this->getProperModuleName($module) . "\\Controllers\\$controllerClassName";
		
		// if the class doesn't exist (autloding enabled)
		if(!class_exists($controllerClassNamespace)) {
			throw new NotFoundHttpException(sprintf(__FUNCTION__ . " : Unable to find controller '%s' for module '%s'", $controller, $module));
		}
		
		// instantiate the controller
		$controllerClass = new $controllerClassNamespace($request);
		$controllerClass->setServiceContainer($this->serviceContainer);

		// get the method name
		$methodName = $this->getActionMethodName($action);
		// if method doesn't exist
		if (!method_exists($controllerClass, $methodName)) {
			throw new NotFoundHttpException(sprintf('ControllerResolver::getController : Method "%s::%s" does not exist.', $controllerClassName, $methodName));
		}
		
		// log controller usage
		if($this->getLoggerService()) {
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
		
		$parameters = array();
		if(is_object($controller)) {
			$r = new \ReflectionObject($controller);
			$parameters = $r->getMethod($method)->getParameters();
		}
		elseif(is_string($controller)) {
			$r = new \ReflectionClass($controller);
			$parameters = $r->getMethod($method)->getParameters();
		}
		
		$arguments = array();
		foreach ($parameters as $param) {
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