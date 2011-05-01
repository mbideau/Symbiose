<?php

namespace Symbiose\Component\Exception;

use Symbiose\Component\Service\ServiceContainerAware,
	Symfony\Bundle\FrameworkBundle\EventDispatcher,
	Symfony\Component\EventDispatcher\Event,
	Symfony\Component\HttpKernel\HttpKernelInterface,
	Symfony\Component\HttpKernel\Exception\FlattenException,
	Symfony\Component\HttpFoundation\Request,
    Swift_Message as Message
;

class Handler
	extends ServiceContainerAware
{
	protected $loggerService;
	protected $requestService;
	protected $responseService;
	protected $mailerService;
	protected $exceptionManagerService;
	protected $httpKernelService;
	
	protected $previousHandler;
	
	public function restoreDefaultHandler()
	{
		restore_exception_handler();
	}
	
	public function restorePreviousHandler()
	{
		set_exception_handler($this->previousHandler);
	}
	
	public function register()
	{
		//$this->defaultHandler = set_exception_handler(array($this, 'handle'));
	}
	
	protected function getDefaultMessage($format)
	{
		$message = $this->serviceContainer->getParameter('exception_handler.default_message.' . $format);
		$message = is_string($message) ? trim($message) : $message;
		switch($format) {
			case 'json' : $message = json_encode($message);
		}
		return $message;
	}
	
	public function handle($exception, $request = null, $returnResponse = false)
	{
		try {
			// if request is not specified, starts with the current request
			if(empty($request)) {
				$request = $this->getRequestService();
			}
			
			// get request format
			$format = 0 === strncasecmp(PHP_SAPI, 'cli', 3) ? 'txt' : $request->getRequestFormat();
			
			// init response
			$response = $this->getResponseService();
			
			// get logger
			$logger = $this->getLoggerService();
			
			if($logger) {
				$logger->err(sprintf('Exception handler: caught exception : %s: %s', get_class($exception), $exception->getMessage()));
			}
			else {
				error_log(sprintf('Exception handler caught exception : %s: %s, %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
			}
			
			// get application environment
			$env = $this->serviceContainer->getParameter('environment.text');
			
			// mail the exception (if in prod)
			if($env == 'production') {
				$mailer = $this->getMailerService();
				if($mailer) {
					// if the exception is not a 'page not found'
					if(strpos(get_class($exception), '\\NotFoundHttpException') === false) {
						$e_message = $exception->getMessage();
						$e_trace = $exception->getTraceAsString();
						$e_file = $exception->getFile();
						$e_line = $exception->getLine();
						// send mail to admin
						$m_subject = '[' . $request->getHttpHost() . $request->getPathInfo() . '] Exception déclenchée';
						$m_from = $this->serviceContainer->getParameter('mailer.default_sender');
						$m_to = $this->serviceContainer->getParameter('mailer.default_receiver');
						$m_body = preg_replace(
							array(
								'#\{e_message\}#',
								'#\{e_trace\}#',
								'#\{e_file\}#',
								'#\{e_line\}#'
							),
							array(
								$e_message,
								$e_trace,
								$e_file,
								$e_line
							),
							$this->getDefaultMessage('report')
						);
						$message = Message::newInstance($m_subject)
							->setFrom($m_from)
							->setTo($m_to)
							->setBody($m_body)
							->setContentType('text/html');
						;
						// send message
						if(!$mailer->send($message)) {
							throw new Exception("Failed to send message.\nSubject:\n$m_subject\nFrom:\n$m_from\nTo:\n$m_to\nBody:\n$m_body", 0, $exception);
						}
					}
				}
			}
		}
		catch(\Exception $ebis) {
			if(!isset($env)) {
				// get application environment
				$env = $this->serviceContainer->getParameter('environment.text');
			}
			// display the exception (if not in production)
			if($env != 'production') {
				echo '<pre>' . var_export(array(
					'Message' => $exception->getMessage(),
					'Trace' => $exception->getTraceAsString(),
					'File' => $exception->getFile(),
					'Line' => $exception->getLine()
				), true) . '</pre>';
			}
			
			error_log(sprintf('Caught PHP Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
			
			// re-throw the exception as this is a catch-all
			throw new \RuntimeException('Exception thrown when handling an exception.' . "\nMessage:\n" . $ebis->getMEssage() . "\nTrace:\n" . $ebis->getTraceAsString(), 0, $exception);
		}
		try {
			// try to get a controller to handle the exception
			$controllerInfos = $this->getExceptionManagerService()->getExceptionRedirection(get_class($exception));
			
        	if(
        		!empty($controllerInfos)
        		&& array_key_exists('module', $controllerInfos)
        		&& array_key_exists('controller', $controllerInfos)
        		&& array_key_exists('action', $controllerInfos)
        	) {
	        	if($logger) {
					$logger->info('Exception handler: redirecting exception handling to : ' . var_export($controllerInfos, true));
				}
        		
        		$attributes = array(
		            '_controller' => $controllerInfos,
		            'exception'   => FlattenException::create($exception),
		            // when using CLI, we force the format to be TXT
		            'format'      => $format
		        );
				
		        $request = $request->duplicate(null, null, $attributes);
				
		        $response = $this->getHttpKernelService()->handle($request, HttpKernelInterface::SUB_REQUEST, true);
        	}
        	else {
        		if($logger) {
					$logger->info('Exception handler: using default response');
				}
        		
        		$this->setDefaultResponseParameters($response, $format);
        	}
			// send (or return) reponse
			return $this->end($response, $returnResponse);
		}
		catch (\Exception $e) {
			if($logger) {
				$logger->err(sprintf('Exception thrown when handling an exception (%s: %s)' . "\n%s", get_class($e), $e->getMessage(), $e->getTraceAsString()));
			}
			try {
				$this->setDefaultResponseParameters($response, $format);
				// send (or return) reponse
				return $this->end($response, $returnResponse);
			}
			catch(\Exception $ebis) {
				// re-throw the exception as this is a catch-all
				throw new \RuntimeException('Exception thrown when handling an exception.', 0, $e);
			}
		}
	}
	
	protected function end($response, $returnResponse)
	{
		if($returnResponse) {
			return $response;
		}
		$response->send();
	}
	
	protected function setDefaultResponseParameters($response, $format)
	{
		// set status code and format
		$response->setStatusCode(500);
		$response->headers->set('Content-type', $this->getEnctype($format));
		// get response content
		$content = $this->getDefaultMessage($format);
		$response->setContent($content);
		return $response;
	}
	
	protected function getEnctype($format)
	{
		$contentType = null;
		switch($format) {
			case 'json' :	$contentType = 'application/json'; break;
			case 'html' :	$contentType = 'text/html'; break;
			case 'xml'	:	$contentType = 'application/xml'; break;
			case 'txt'	:	$contentType = 'text/plain'; break;
		}
		return $contentType;
	}
}