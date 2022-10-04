<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

/**
 * Trace store.
 *
 * Will be used by the StoreHandler implementation for storing profiling
 * information in whatever backend you like.
 */
interface TraceStore
{
    /**
     * Store trace information.
     */
    public function store(ProfilerTrace ...$traces): void;
}
