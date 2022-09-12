<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerTrace;
use MakinaCorpus\Profiling\TraceHandler;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

/**
 * Composer require sentry/sentry:^3.8
 */
class SentryHandler implements TraceHandler
{
    private HubInterface $hub;
    /** @param Span */
    private array $spans = [];

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    /**
     * {@inheritdoc}
     */
    public function onStart(Profiler $profiler): void
    {
        $transaction = $this->hub->getTransaction();

        if (!$transaction) {
            return;
        }

        $spanContext = new SpanContext();
        $spanContext->setOp($profiler->getAbsoluteName());

        $this->spans[$profiler->getId()] = $transaction->startChild($spanContext);
    }

    /**
     * {@inheritdoc}
     */
    public function onStop(ProfilerTrace $trace): void
    { 
        $id = $trace->getId();

        $span = $this->spans[$id] ?? null;

        // @todo Alter when error?
        if ($span) {
            \assert($span instanceof Span);

            if ($description = $trace->getDescription()) {
                $span->setDescription($description);
            }
            $span->finish();

            unset($this->spans[$id]);
        }
    }

    /**
     * Flush any remaining buffer.
     */
    public function flush(): void
    {
        foreach ($this->spans as $span) {
            if ($span) {
                \assert($span instanceof Span);

                $span->finish();
            }
        }

        $this->spans = [];
    }
}
