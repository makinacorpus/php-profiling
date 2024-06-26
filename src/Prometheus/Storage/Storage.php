<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Storage;

use MakinaCorpus\Profiling\Prometheus\Output\SampleCollection;
use MakinaCorpus\Profiling\Prometheus\Sample\Sample;
use MakinaCorpus\Profiling\Prometheus\Schema\Schema;

// @todo IDE bug, sorry.
\class_exists(Sample::class);
\class_exists(SampleCollection::class);

interface Storage
{
    /**
     * Collect all samples.
     *
     * @return SampleCollection[]
     */
    public function collect(Schema $schema): iterable;

    /**
     * Store everything.
     *
     * @param Sample[] $samples
     */
    public function store(Schema $schema, iterable $samples): void;

    /**
     * Clean outdated samples.
     */
    public function cleanOutdatedSamples(): void;

    /**
     * Delete all.
     */
    public function wipeOutData(): void;

    /**
     * Enable to disable automatic schema creation.
     *
     * If not called, it must be disabled per default.
     */
    public function toggleAutoSchemaCreate(bool $toggle = true): void;

    /**
     * Create schema if not already created, do nothing otherwise.
     */
    public function ensureSchema(): void;
}
