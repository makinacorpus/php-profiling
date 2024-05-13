<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Storage;

/**
 * The right schema is first, some meta information, such as:
 *     # HELP node_scrape_collector_duration_seconds node_exporter: Duration of a collector scrape.
 *     # TYPE node_scrape_collector_duration_seconds gauge
 * Then, some values:
 *     node_scrape_collector_duration_seconds{collector="arp"} 0.000121002
 *     node_scrape_collector_duration_seconds{collector="bcache"} 1.5697e-05
 *     node_scrape_collector_duration_seconds{collector="bonding"} 3.1096e-05
 *     node_scrape_collector_duration_seconds{collector="btrfs"} 0.001315575
 *
 * For all counters, we will store a single line for all samples their metadata
 * along the value. For "counter" and "gauge", this is trivial since there is
 * always only a single value.
 *
 * Considering "summary" and "histogram", value is more complex, but we still
 * will store a single line.
 */
abstract class AbstractStorage implements Storage
{
    private bool $schemaCreated = false;
    private bool $autoSchemaCreate = false;

    #[\Override]
    public final function toggleAutoSchemaCreate(bool $toggle = true): void
    {
        $this->autoSchemaCreate = $toggle;
    }

    #[\Override]
    public final function ensureSchema(): void
    {
        if ($this->schemaCreated) {
            return;
        }

        try {
            $this->doEnsureSchema();
        } finally {
            $this->schemaCreated = true;
        }
    }

    /**
     * Implement the ensureSchema() method here.
     */
    protected abstract function doEnsureSchema(): void;

    /**
     * Run this method in every other method first.
     */
    protected function checkSchema(): void
    {
        if ($this->schemaCreated || !$this->autoSchemaCreate) {
            return;
        }

        try {
            $this->doEnsureSchema();
        } finally {
            $this->schemaCreated = true;
        }
    }
}
