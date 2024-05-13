<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Storage;

use MakinaCorpus\Profiling\Prometheus\Schema\Schema;

class NullStorage implements Storage
{
    #[\Override]
    public function collect(Schema $schema): iterable
    {
        return [];
    }

    #[\Override]
    public function store(Schema $schema, iterable $samples): void
    {
    }

    #[\Override]
    public function cleanOutdatedSamples(): void
    {
    }

    #[\Override]
    public function wipeOutData(): void
    {
    }

    #[\Override]
    public function toggleAutoSchemaCreate(bool $toggle = true): void
    {
    }

    #[\Override]
    public function ensureSchema(): void
    {
    }
}
