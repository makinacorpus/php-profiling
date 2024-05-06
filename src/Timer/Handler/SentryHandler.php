<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Handler;

use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\Timer\TimerTrace;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

/**
 * Composer require sentry/sentry:^3.8
 */
class SentryHandler extends AbstractHandler
{
    private HubInterface $hub;
    /** @var Span[] */
    private array $spans = [];

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    #[\Override]
    public function onStart(Timer $timer): void
    {
        $transaction = $this->hub->getTransaction();

        if (!$transaction) {
            return;
        }

        $spanContext = new SpanContext();
        $spanContext->setOp($timer->getAbsoluteName());

        $this->spans[$timer->getId()] = $transaction->startChild($spanContext);
    }

    #[\Override]
    public function onStop(TimerTrace $trace): void
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

    #[\Override]
    public function flush(): void
    {
        foreach ($this->spans as $span) {
            \assert($span instanceof Span);
            $span->finish();
        }

        $this->spans = [];
    }
}
