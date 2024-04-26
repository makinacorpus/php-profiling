<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\Handler\Formatter\PlainTextFormatter;
use MakinaCorpus\Profiling\TimerTrace;

abstract class AbstractFormatterHandler extends AbstractHandler
{
    private bool $started = false;
    private ?Formatter $formatter = null;

    /**
     * Get formatter instance.
     */
    protected function getFormatter(): Formatter
    {
        return $this->formatter ?? ($this->formatter = new PlainTextFormatter());
    }

    /**
     * Format timer trace.
     */
    protected function format(TimerTrace $trace): string
    {
        if (!$this->started) {
            $this->started = true;
        }
        return $this->getFormatter()->format($trace);
    }

    /**
     * Set formatter.
     */
    public function setFormatter(Formatter $formatter): void
    {
        if ($this->started) {
            throw new \LogicException("Cannot change formatter if output has started.");
        }
        $this->formatter = $formatter;
    }
}
