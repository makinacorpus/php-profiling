<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Storage;

use MakinaCorpus\Profiling\Prometheus\Error\StorageError;
use MakinaCorpus\Profiling\Prometheus\Output\SampleCollection;
use MakinaCorpus\Profiling\Prometheus\Output\SummaryOutput;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Sample;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use MakinaCorpus\Profiling\Prometheus\Schema\Schema;
use MakinaCorpus\Profiling\Prometheus\Schema\SummaryMeta;
use MakinaCorpus\QueryBuilder\BridgeFactory;
use MakinaCorpus\QueryBuilder\DatabaseSession;
use MakinaCorpus\QueryBuilder\Error\Server\TableDoesNotExistError;
use MakinaCorpus\QueryBuilder\ExpressionFactory;
use MakinaCorpus\QueryBuilder\Result\ResultRow;
use MakinaCorpus\QueryBuilder\TableExpression;
use MakinaCorpus\QueryBuilder\Vendor;

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
class QueryBuilderStorage implements Storage
{
    private ?DatabaseSession $databaseSession = null;
    private bool $tableChecked = false;
    private array $meta = [];

    public function __construct(
        private string $databaseUri,
        private string $tableName = 'public.prometheus_metrics'
    ) {}

    #[\Override]
    public function collect(Schema $schema): iterable
    {
        $this->checkTable();
        $this->cleanOutdatedSamples();

        $databaseSession = $this->getDatabaseSession();

        // Gauge.
        yield from $databaseSession
            ->executeQuery(
                <<<SQL
                SELECT
                    meta.name,
                    meta.labels,
                    meta.help,
                    array_agg(json_build_object('value', sample.value, 'labels', sample.labels)) AS samples
                FROM ? meta
                JOIN ? sample
                    ON sample.name = meta.name
                GROUP BY meta.name
                SQL,
                [$this->getTable('gauge_meta'), $this->getTable('gauge')]
            )
            ->setHydrator(function (ResultRow $row) {
                $name = $row->get('name', 'string');

                $samples = [];
                foreach ($row->get('samples', 'string[]') as $data) {
                    $data = \json_decode($data, true);
                    $samples[] = (new Gauge($name, $data['labels'], []))->set((float) $data['value']);
                }

                return new SampleCollection(
                    name: $name,
                    help: $row->get('help', 'string') ?? $name,
                    type: 'gauge',
                    labelNames: $row->get('labels', 'string[]'),
                    samples: $samples,
                );
            })
        ;

        // Counter.
        yield from $databaseSession
            ->executeQuery(
                <<<SQL
                SELECT
                    meta.name,
                    meta.labels,
                    meta.help,
                    array_agg(json_build_object('value', sample.value, 'labels', sample.labels)) AS samples
                FROM ? meta
                JOIN ? sample
                    ON sample.name = meta.name
                GROUP BY meta.name
                SQL,
                [$this->getTable('counter_meta'), $this->getTable('counter')]
            )
            ->setHydrator(function (ResultRow $row) {
                $name = $row->get('name', 'string');

                $samples = [];
                foreach ($row->get('samples', 'string[]') as $data) {
                    $data = \json_decode($data, true);
                    $samples[] = (new Counter($name, $data['labels'], []))->increment((int) $data['value']);
                }

                return new SampleCollection(
                    name: $name,
                    help: $row->get('help', 'string') ?? $name,
                    type: 'counter',
                    labelNames: $row->get('labels', 'string[]'),
                    samples: $samples,
                );
            })
        ;

        // Summary.
        $result = $databaseSession->executeQuery(
            <<<SQL
            SELECT
                meta.name,
                meta.labels,
                meta.help,
                meta.quantiles,
                meta.max_age_seconds,
                array_agg(json_build_object('value', sample.value, 'labels', sample.labels)) AS samples
            FROM ? meta
            JOIN ? sample
                ON sample.name = meta.name
            GROUP BY meta.name
            SQL,
            [$this->getTable('summary_meta'), $this->getTable('summary')]
        );

        foreach ($result as $row) {
            $name = $row->get('name', 'string');
            $quantiles = $row->get('quantiles', 'float[]') ?? SummaryMeta::getDefaultQuantiles();

            // First aggregate all values.
            $values = [];
            foreach ($row->get('samples', 'string[]') as $data) {
                $data = \json_decode($data, true);
                $key = \implode(':', $data['labels']);
                $values[$key][] = (int) $data['value'];
            }

            // Then compute quantiles and build sample list.
            $samples = (function () use ($name, $values, $quantiles) {
                foreach ($values as $key => $values) {
                    $labels = \explode(':', $key);
                    \sort($values);

                    foreach ($quantiles as $quantile) {
                        // Compute quantiles and set a summary sample in list for
                        // each computed quantile.
                        yield (new SummaryOutput($name, $labels, [], SummaryMeta::computeQuantiles($values, $quantile), $quantile));
                    }

                    yield (new Counter($name . '_count', $labels, []))->increment(\count($values));
                    yield (new Gauge($name . '_sum', $labels, []))->set(\array_sum($values));
                }
            })();

            yield new SampleCollection(
                name: $name,
                help: $row->get('help', 'string') ?? $name,
                type: 'summary',
                labelNames: $row->get('labels', 'string[]'),
                samples: $samples,
            );
        }
    }

    #[\Override]
    public function store(Schema $schema, iterable $samples): void
    {
        if (!$samples) {
            return;
        }

        $this->checkTable();
        $databaseSession = $this->getDatabaseSession();

        $counterItems = $gaugeItems = $summaryItems = [];
        $counterMeta = $gaugeMeta = $summaryMeta = [];

        foreach ($samples as $sample) {
            \assert($sample instanceof Sample);
            $name = $sample->name;
            $namespacedName = $schema->getNamespace() . '_' . $name;
            $labelValues = $sample->labelValues;

            if ($sample instanceof Counter) {
                $meta = $schema->getCounter($name);

                if (!\array_key_exists($name, $counterMeta)) {
                    $counterMeta[$name] = ExpressionFactory::row([
                        $namespacedName,
                        ExpressionFactory::value($meta->getLabelNames(), 'text[]'),
                        $meta->getHelp(),
                    ]);
                }

                $counterItems[] = ExpressionFactory::row([
                    $namespacedName,
                    ExpressionFactory::value($labelValues, 'text[]'),
                    $sample->getValue(),
                    $sample->measuredAt,
                ]);
            } else if ($sample instanceof Gauge) {
                $meta = $schema->getGauge($name);

                if (!\array_key_exists($name, $gaugeMeta)) {
                    $gaugeMeta[$name] = ExpressionFactory::row([
                        $namespacedName,
                        ExpressionFactory::value($meta->getLabelNames(), 'text[]'),
                        $meta->getHelp(),
                    ]);
                }

                $gaugeItems[] = ExpressionFactory::row([
                    $namespacedName,
                    ExpressionFactory::value($labelValues, 'text[]'),
                    $sample->getValue(),
                    $sample->measuredAt,
                ]);
            } else if ($sample instanceof Summary) {
                $meta = $schema->getSummary($name);

                if (!\array_key_exists($name, $summaryMeta)) {
                    $summaryMeta[$name] = ExpressionFactory::row([
                        $namespacedName,
                        ExpressionFactory::value($meta->getLabelNames(), 'text[]'),
                        $meta->getHelp(),
                        $meta->getMaxAge(),
                        ExpressionFactory::value($meta->getQuantiles(), 'float[]'),
                    ]);
                }

                foreach ($sample->getValues() as $sampleValue) {
                    $validUntil = $sampleValue->measuredAt->add(new \DateInterval(\sprintf('PT%dS', $meta->getMaxAge())));

                    $summaryItems[] = ExpressionFactory::row([
                        $namespacedName,
                        ExpressionFactory::value($labelValues, 'text[]'),
                        $sampleValue->value,
                        $validUntil,
                    ]);
                }
            } else {
                \trigger_error(\sprintf("Sample of type '%s' is not supported.", \get_class($sample)));
            }
        }

        if ($counterItems) {
            $databaseSession->executeStatement(
                <<<SQL
                INSERT INTO ? (
                    "name", "labels", "help"
                )
                ?
                ON CONFLICT ("name")
                    DO UPDATE SET
                        "labels" = excluded."labels",
                        "help" = excluded."help"
                SQL,
                [
                    $this->getTable('counter_meta'),
                    ExpressionFactory::constantTable($counterMeta),
                ]
            );

            $databaseSession->executeStatement(
                <<<SQL
                INSERT INTO ? (
                    "name", "labels", "value", "updated"
                )
                ?
                ON CONFLICT ("name", "labels")
                    DO UPDATE SET
                        "value" = ?."value" + excluded."value"
                SQL,
                [
                    $this->getTable('counter'),
                    ExpressionFactory::constantTable($counterItems),
                    $this->getTable('counter'),
                ]
            );
        }

        if ($gaugeItems) {
            $databaseSession->executeStatement(
                <<<SQL
                INSERT INTO ? (
                    "name", "labels", "help"
                )
                ?
                ON CONFLICT ("name")
                    DO UPDATE SET
                        "labels" = excluded."labels",
                        "help" = excluded."help"
                SQL,
                [
                    $this->getTable('gauge_meta'),
                    ExpressionFactory::constantTable($gaugeMeta),
                ]
            );

            $databaseSession->executeStatement(
                <<<SQL
                INSERT INTO ? (
                    "name", "labels", "value", "updated"
                ) 
                ?
                ON CONFLICT ("name", "labels")
                    DO UPDATE SET
                        "value" = excluded."value"
                SQL,
                [
                    $this->getTable('gauge'),
                    ExpressionFactory::constantTable($gaugeItems),
                    ExpressionFactory::raw('current_timestamp'),
                ]
            );
        }

        if ($summaryItems) {
            $databaseSession->executeStatement(
                <<<SQL
                INSERT INTO ? (
                    "name", "labels", "help", "max_age_seconds", "quantiles"
                )
                ?
                ON CONFLICT ("name")
                    DO UPDATE SET
                        "labels" = excluded."labels",
                        "help" = excluded."help",
                        "max_age_seconds" = excluded."max_age_seconds",
                        "quantiles" = excluded."quantiles"
                SQL,
                [
                    $this->getTable('summary_meta'),
                    ExpressionFactory::constantTable($summaryMeta),
                ]
            );

            $databaseSession->executeStatement(
                <<<SQL
                INSERT INTO ? (
                    "name", "labels", "value", "valid_until"
                )
                ?
                SQL,
                [
                    $this->getTable('summary'),
                    ExpressionFactory::constantTable($summaryItems),
                ]
            );
        }
    }

    #[\Override]
    public function cleanOutdatedSamples(): void
    {
        $this->checkTable();

        $this->getDatabaseSession()->executeStatement(
            <<<SQL
            DELETE FROM ?
            WHERE
                valid_until < current_timestamp
            SQL,
            [$this->getTable('summary')]
        );
    }

    #[\Override]
    public function wipeOutData(): void
    {
        $this->checkTable();

        try {
            $this
                ->getDatabaseSession()
                ->delete($this->getTable())
                ->executeStatement()
            ;
        } catch (\Throwable $e) {
            throw new StorageError($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get database session.
     */
    private function getDatabaseSession(): DatabaseSession
    {
        return $this->databaseSession ??= BridgeFactory::create($this->databaseUri);
    }

    /**
     * Get table name.
     */
    private function getTable(?string $suffix = null): TableExpression
    {
        return ExpressionFactory::table($suffix ? $this->tableName . '_' . $suffix : $this->tableName);
    }

    /**
     * Ensure tables exists.
     */
    private function checkTable(): void
    {
        if ($this->tableChecked) {
            return;
        }

        $this->tableChecked = true;
        $databaseSession = $this->getDatabaseSession();
        if (!$databaseSession->vendorIs(Vendor::POSTGRESQL)) {
            throw new StorageError("Only 'pgsql' driver is supported.");
        }

        $tables = [
            'counter_meta' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "help" text DEFAULT NULL,
                    PRIMARY KEY ("name")
                )
                SQL,
            'counter' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "value" int NOT NULL DEFAULT 0,
                    "updated" timestamp with time zone NOT NULL,
                    PRIMARY KEY ("name", "labels")
                )
                SQL,
            'gauge_meta' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "help" text DEFAULT NULL,
                    PRIMARY KEY ("name")
                )
                SQL,
            'gauge' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "value" float NOT NULL DEFAULT 0,
                    "updated" timestamp with time zone NOT NULL,
                    PRIMARY KEY ("name", "labels")
                )
                SQL,
            'summary_meta' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "help" text DEFAULT NULL,
                    "max_age_seconds" int NOT NULL DEFAULT 600,
                    "quantiles" float[] NOT NULL,
                    PRIMARY KEY ("name")
                )
                SQL,
            'summary' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "value" float,
                    "valid_until" timestamp with time zone NOT NULL
                )
                SQL,
        ];

        foreach ($tables as $suffix => $createStatement) {
            $table = $this->getTable($suffix);
            try {
                try {
                    $databaseSession->executeStatement('SELECT 1 FROM ?', [$table]);
                } catch (TableDoesNotExistError $e) {
                    $databaseSession->executeStatement($createStatement, [$table]);
                }
            } catch (\Throwable $e) {
                throw new StorageError($e->getMessage(), $e->getCode(), $e);
            }
        }
    }
}
