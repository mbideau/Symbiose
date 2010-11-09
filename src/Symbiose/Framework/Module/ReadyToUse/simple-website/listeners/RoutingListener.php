<?php

namespace Modules\SimpleWebsite\Listeners;

use Symfony\Component\HttpKernel\Log\LoggerInterface,
	Symfony\Component\EventDispatcher\Event,
	Symfony\Bundle\FrameworkBundle\EventDispatcher,
	Symfony\Component\Routing\RouterInterface,
	Symfony\Component\HttpKernel\HttpKernelInterface,
	Falcon\Site\Component\Service\ServiceContainerAware
;

/**
 * RequestListener
 */
class RoutingListener
	extends ServiceContainerAware
{
    protected $routerService;
    protected $loggerService;

    /**
     * Registers a core.request listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.request', array($this, 'resolve'), $priority);
    }

    public function resolve(Event $event)
    {
        $request = $event->getParameter('request');

        if (HttpKernelInterface::MASTER_REQUEST === $event->getParameter('request_type')) {
            // set the context even if the parsing does not need to be done
            // to have correct link generation
            $this->getRouterService()->setContext(array(
                'base_url'  => $request->getBaseUrl(),
                'method'    => $request->getMethod(),
                'host'      => $request->getHost(),
                'is_secure' => $request->isSecure(),
            ));
        }

        if ($request->attributes->has('_controller')) {
            return;
        }

        if (false !== $parameters = $this->getRouterService()->match($request->getPathInfo())) {
            if (null !== $this->getLoggerService()) {
                $this->getLoggerService()->info(sprintf('Matched route "%s" (parameters: %s)', $parameters['_route'], str_replace("\n", '', var_export($parameters, true))));
            }

            $request->attributes->replace($parameters);
        }
        elseif (null !== $this->getLoggerService()) {
            $this->getLoggerService()->err(sprintf('No route found for %s', $request->getPathInfo()));
        }
    }
}
