<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Helper;

trait WithPidTrait
{
    private ?int $pid = null;

    /**
     * Get current process identifier.
     */
    protected function getPid(): int
    {
        return $this->pid ?? ($this->pid = \getmypid());
    }
}
