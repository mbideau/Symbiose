<?php

namespace Modules\SimpleWebsite\Listeners;

use \RuntimeException as Exception,
	Symfony\Bundle\FrameworkBundle\EventDispatcher,
	Symfony\Component\EventDispatcher\Event,
	Symfony\Component\HttpKernel\HttpKernelInterface,
	Symfony\Component\HttpKernel\Exception\FlattenException,
	Symfony\Component\HttpFoundation\Request,
	Symbiose\Component\Service\ServiceContainerAware
;

class ExceptionListener
	extends ServiceContainerAware
{
	protected $loggerService;
	protected $exceptionManagerService;
	
	/**
     * Registers a core.exception listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.exception', array($this, 'resolve'), $priority);
    }
	
    /**
     * 
     * @param $event
     */
    public function resolve(Event $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getParameter('request_type')) {
            return false;
        }

        $request = $event->getParameter('request');
        $exception = $event->getParameter('exception');
        echo '<pre>' . var_export(array(
        	'Exception message' => $exception->getMessage(),
        	'Exception trace' => $exception->getTraceAsString()
        ), true) . '</pre>';
        if($this->getExceptionManagerService()) {
        	$controller = $this->getExceptionManagerService()->getExceptionRedirection(get_class($exception));
        	
        	if(!empty($controller)) {
	        	if (null !== $this->getLoggerService()) {
		            $this->getLoggerService()->err(sprintf('%s: %s (uncaught exception)', get_class($exception), $exception->getMessage()));
		        }
		        else {
		            error_log(sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
		        }
		
		        $logger = null !== $this->getLoggerService() ? $this->getLoggerService()->getDebugLogger() : null;
		
		        $attributes = array(
		            '_controller' => $controller,
		            'exception'   => FlattenException::create($exception),
		            'logger'      => $logger,
		            // when using CLI, we force the format to be TXT
		            'format'      => 0 === strncasecmp(PHP_SAPI, 'cli', 3) ? 'txt' : $request->getRequestFormat(),
		        );
		
		        $request = $request->duplicate(null, null, $attributes);
	
		        try {
		            $response = $event->getSubject()->handle($request, HttpKernelInterface::SUB_REQUEST, true);
		        }
		        catch (\Exception $e) {
		            if (null !== $this->getLoggerService()) {
		                $this->getLoggerService()->err(sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $e->getMessage()));
		            }
		
		            // re-throw the exception as this is a catch-all
		            throw new \RuntimeException('Exception thrown when handling an exception.', 0, $e);
		        }
		
		        return $response;
        	}
        }
        return true;
    }
}