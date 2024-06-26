<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Storage;

interface TraceStoreRegistry
{
    /**
     * Get list of existing stores.
     *
     * @return string[]
     */
    public function list(): array;

    /**
     * Get single instance.
     */
    public function get(string $name): TraceStore;
}
