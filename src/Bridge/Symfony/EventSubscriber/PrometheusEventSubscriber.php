<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\EventSubscriber;

use MakinaCorpus\Profiling\Helper\Matcher;
use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Prometheus\Collector\SysInfoCollector;
use MakinaCorpus\Profiling\RequestContext;
use MakinaCorpus\Profiling\Timer;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PrometheusEventSubscriber implements EventSubscriberInterface
{
    private ?Timer $started = null;

    public function __construct(
        private Profiler $profiler,
        private ?Matcher $ignoredCommands = null,
        private ?Matcher $ignoredRoutes = null,
        private array $ignoredMethods = ['OPTION'],
        private ?SysInfoCollector $sysInfoCollector = null,
    ) {
        ($this->ignoredRoutes ??= new Matcher())->addPattern('prometheus_metrics');
    }

    #[\Override]
    public static function getSubscribedEvents()
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 2048],
            ConsoleEvents::ERROR => ['onConsoleException'],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate'],
            KernelEvents::REQUEST => [
                ['onKernelRequestPre', 2048],
                ['onKernelRequest'],
            ],
            KernelEvents::EXCEPTION => ['onKernelException'],
            KernelEvents::TERMINATE => ['onKernelTerminate'],
        ];
    }

    /**
     * Intercept request, hope being the first, initializes metris and request timer.
     */
    public function onKernelRequestPre(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->started = $this->profiler->timer('request');
    }

    /**
     * There is no route when being the first, so check the route from here.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $context = RequestContext::request($event->getRequest());

        if (\in_array($context->method, $this->ignoredMethods) || $this->ignoredRoutes?->match($context->route)) {
            $this->profiler->enterContext($context, false);
        } else {
            $this->profiler->enterContext($context, true);
            $this->profiler->gauge('instance_name', [], 1);
            $this->profiler->counter('http_request_total', [$context->route, $context->method], 1);
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if ($this->profiler->isPrometheusEnabled()) {
            $context = $this->profiler->getContext();
            $exception = $event->getThrowable();
            $this->profiler->counter('http_exception_total', [$context->route, $context->method, RequestContext::name($exception)], 1);
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        try {
            $timer = $this->profiler->timer('profiling-terminate');

            if ($this->profiler->isPrometheusEnabled()) {
                $context = $this->profiler->getContext();
                $response = $event->getResponse();
                $responseStatus = $response->getStatusCode();
                $labels = [$context->route, $context->method, $responseStatus];
                if (null !== $this->started) {
                    $this->profiler->summary('http_request_duration_msec', $labels, $this->started->getElapsedTime());
                }
                $this->profiler->summary('http_memory_consuption', $labels, \memory_get_peak_usage(true));
                $this->profiler->counter('http_response_total', $labels, 1);
                $this->sysInfoCollector?->collect();
            }

            $this->profiler->exitContext();
        } finally {
            $timer?->stop();
        }
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->started = $this->profiler->timer('command');
        $context = RequestContext::command($event->getCommand()->getName());

        if ($this->ignoredCommands?->match($context->route)) {
            $this->profiler->enterContext($context, false);
        } else {
            $this->profiler->enterContext($context, true);
            $this->profiler->counter('console_command_total', [$context->route, $context->method], 1);
        }
    }

    public function onConsoleException(ConsoleErrorEvent $event): void
    {
        if ($this->profiler->isPrometheusEnabled()) {
            $context = $this->profiler->getContext();
            $exception = $event->getError();
            $this->profiler->counter('console_exception_total', [$context->route, $context->method, RequestContext::name($exception)], 1);
        }
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        try {
            $timer = $this->profiler->timer('profiling-terminate');

            if (!$this->profiler->isPrometheusEnabled()) {
                $context = $this->profiler->getContext();
                $status = $event->getExitCode();
                $labels = [$context->route, $context->method, $status];
                if (null !== $this->started) {
                    $this->profiler->summary('console_duration_msec', $labels, $this->started->getElapsedTime());
                }
                $this->profiler->summary('console_memory_consuption', $labels, \memory_get_peak_usage(true));
                $this->profiler->counter('console_status_total', $labels, 1);
                $this->sysInfoCollector?->collect();
            }

            $this->profiler->exitContext();
        } finally {
            $timer?->stop();
        }
    }
}
