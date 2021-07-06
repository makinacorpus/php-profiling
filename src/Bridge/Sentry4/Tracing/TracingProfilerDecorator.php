<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Sentry4\Tracing;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Implementation\AbstractProfilerDecorator;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;

final class TracingProfilerDecorator extends AbstractProfilerDecorator
{
    private ?Transaction $transaction;
    private ?Span $span = null;
    private int $depth;

    public function __construct(?Transaction $transaction, Profiler $decorated, int $depth)
    {
        parent::__construct($decorated);

        $this->depth = $depth;
        $this->transaction = $transaction;

        if (null !== $this->transaction) {
            $spanContext = new SpanContext();
            $spanContext->setOp($decorated->getAbsoluteName());

            $this->span = $this->transaction->startChild($spanContext);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function createDecorator(Profiler $decorated, ?string $name = null): self
    {
        return new TracingProfilerDecorator($this->transaction, $decorated, $this->depth + 1);
    }

    /**
     * Stop decorated instance.
     */
    protected function doStop(): void
    {
        if (null !== $this->span) {
            if ($description = $this->getDescription()) {
                $this->span->setDescription($description);
            }

            $this->span->finish();
        }
    }
}
