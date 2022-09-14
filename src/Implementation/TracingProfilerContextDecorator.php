<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\TraceHandler;

/**
 * Emits profiler traces into registered trace handlers.
 */
final class TracingProfilerContextDecorator extends AbstractProfilerContextDecorator
{
    /** @var array<string,TraceHandler> */
    private array $handlers;
    /** @var array<string,bool> */
    private array $catchAll = [];
    /** @var array<string,string[]> */
    private array $whiteList = [];
    /** @var array<string,string[]> */
    private array $blackList = [];

    /**
     * @param array<string,TraceHandler> $handlers
     *   Keys are handler names, values are instances of TraceHandler.
     * @param array<string,string[]> $handlerChannelMap
     *   Keys are handler names, values are channel names. Each channel name
     *   can be prefixed using "!" case in which the list will be considered
     *   as a blacklist.
     */
    public function __construct(ProfilerContext $decorated, array $handlers, array $handlerChannelMap)
    {
        parent::__construct($decorated);

        // Validate handler and pre-build the catch-all list.
        foreach ($handlers as $handlerName => $handler) {
            if (!$handler instanceof TraceHandler) {
                throw new \InvalidArgumentException(\sprintf("Handler '%s' is not a %s instance.", $handlerName, TraceHandler::class));
            }
            $this->handlers[$handlerName] = $handler;
            $this->catchAll[$handlerName] = true;
        }

        // Build optimised list, fix the catch-all list.
        foreach ($handlerChannelMap as $handlerName => $channels) {
            if (!isset($this->handlers[$handlerName])) {
                continue;
            }

            foreach (\array_unique($channels) as $channel) {
                if ('!' === $channel[0]) {
                    $this->blackList[$handlerName][\substr($channel, 1)] = true;
                } else {
                    // Default handler cannot have a whitelist, default handler
                    // is always catch-all, but it can have a blacklist.
                    $this->whiteList[$handlerName][$channel] = true;
                    $this->catchAll[$handlerName] = false;
                }
            }
        }
    }

    /**
     * Find handlers for given profiler.
     *
     * @return TraceHandler[]
     */
    private function findHandlers(Profiler $profiler): array
    {
        $ret = [];

        $traceChannels = $profiler->getChannels();
        if (empty($traceChannels)) {
            foreach ($this->catchAll as $handlerName => $enabled) {
                if ($enabled) {
                    $ret[] = $this->handlers[$handlerName];
                }
            }

            return $ret;
        }

        foreach ($this->handlers as $handlerName => $handler) {
            \assert($handler instanceof TraceHandler);

            $doHandle = false;
            $blackList = $this->blackList[$handlerName] ?? [];
            $whiteList = $this->whiteList[$handlerName] ?? [];

            if ($this->catchAll[$handlerName]) {
                // For catch-all, handle only black list.
                if ($blackList) {
                    foreach ($traceChannels as $channel) {
                        if (!isset($blackList[$channel])) {
                            $doHandle = true;
                            // The profiler trace could have more than one
                            // channel, if any is not blacklisted, just send it.
                            break;
                        }
                    }
                } else {
                    // Black list is empty, send it.
                    $doHandle = true;
                }
            } else {
                // For white-list, handle only white-list.
                foreach ($traceChannels as $channel) {
                    if (isset($whiteList[$channel])) {
                        $doHandle = true;
                        break;
                    }
                }
            }

            if ($doHandle) {
                $ret[] = $handler;
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function create(?string $name = null, ?array $channels = null): Profiler
    {
        $ret = $this->decorated->create($name, $channels);

        if (!$ret instanceof DefaultProfiler) {
            throw new \LogicException(\sprintf("Profiler must be an instanceof %s", DefaultProfiler::class));
        }

        $handlers = $this->findHandlers($ret);

        return $ret
            ->addStartCallback(function (Profiler $profiler) use ($handlers) {
                foreach ($handlers as $handler) {
                    \assert($handler instanceof TraceHandler);
                    $handler->onStart($profiler);
                }
            })
            ->addStopCallback(function (Profiler $profiler) use ($handlers) {
                foreach ($handlers as $handler) {
                    \assert($handler instanceof TraceHandler);
                    $handler->onStop($profiler);
                }
            })
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null, ?array $channels = null): Profiler
    {
        return $this->create($name, $channels)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): iterable
    {
        $ret = $this->decorated->flush();

        foreach ($this->handlers as $handler) {
            \assert($handler instanceof TraceHandler);
            $handler->flush();
        }

        return $ret;
    }
}
