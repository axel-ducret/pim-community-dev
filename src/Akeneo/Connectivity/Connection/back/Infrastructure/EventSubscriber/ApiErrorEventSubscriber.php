<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Infrastructure\EventSubscriber;

use Akeneo\Connectivity\Connection\Infrastructure\ErrorManagement\CollectApiError;
use Akeneo\Connectivity\Connection\Infrastructure\ErrorManagement\MonitoredRoutes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class ApiErrorEventSubscriber implements EventSubscriberInterface
{
    /** @var RequestStack */
    private $requestStack;

    /** @var CollectApiError */
    private $collectApiError;

    public function __construct(RequestStack $requestStack, CollectApiError $collectApiError)
    {
        $this->requestStack = $requestStack;
        $this->collectApiError = $collectApiError;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'collectApiError',
            KernelEvents::TERMINATE => 'saveApiErrors',
        ];
    }

    public function collectApiError(ExceptionEvent $exceptionEvent): void
    {
        // The '_route' property can only be found on the MasterRequest.
        $request = $exceptionEvent->isMasterRequest()
            ? $exceptionEvent->getRequest()
            : $this->requestStack->getMasterRequest();

        if (false === $this->isMonitoredRoute($request)) {
            return;
        }

        $this->collectApiError->collectFromHttpException($exceptionEvent->getException());
    }

    public function saveApiErrors(TerminateEvent $terminateEvent): void
    {
        $request = $terminateEvent->getRequest();

        if (false === $this->isMonitoredRoute($request)) {
            return;
        }

        $this->collectApiError->save();
    }

    private function isMonitoredRoute(Request $request): bool
    {
        if (null === $route = $request->get('_route')) {
            return false;
        }

        return in_array($route, MonitoredRoutes::ROUTES);
    }
}
