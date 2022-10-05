<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

use MakinaCorpus\Profiling\Store\TraceQuery;

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

    /**
     * Delete all traces.
     *
     * Store implementations might not implement this method, it will be used
     * for user interface only.
     *
     * @throws \LogicException
     *   If the store cannot clear.
     */
    public function clear(?\DateTimeInterface $until = null): void;

    /**
     * Query traces.
     *
     * Store implementations might not implement this method, it will be used
     * for user interface only.
     *
     * @throws \LogicException
     *   If the store cannot clear.
     */
    public function query(): TraceQuery;
}
