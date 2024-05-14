<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Storage;

use MakinaCorpus\Profiling\Prometheus\Error\StorageError;
use MakinaCorpus\Profiling\Prometheus\Output\SampleCollection;
use MakinaCorpus\Profiling\Prometheus\Output\SummaryOutput;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Histogram;
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

class QueryBuilderStorage extends AbstractStorage
{
    private ?DatabaseSession $databaseSession = null;
    private bool $tableChecked = false;

    public function __construct(
        private string $databaseUri,
        private string $tableName = 'public.prometheus_metrics',
    ) {}

    #[\Override]
    public function collect(Schema $schema): iterable
    {
        $this->checkSchema();
        $this->cleanOutdatedSamples();

        $databaseSession = $this->getDatabaseSession();

        // Gauge.
        yield from $databaseSession
            ->executeQuery(
                <<<SQL
                SELECT
                    name, array_agg(json_build_object('value', value, 'labels', labels)) AS samples
                FROM ? sample
                GROUP BY name
                SQL,
                [$this->getTable('gauge')]
            )
            ->setHydrator(function (ResultRow $row) use ($schema) {
                $name = $row->get('name', 'string');
                $meta = $schema->getGauge($name, true);

                $samples = [];
                foreach ($row->get('samples', 'string[]') as $data) {
                    $data = \json_decode($data, true);
                    $samples[] = (new Gauge($name, $data['labels'], []))->set((float) $data['value']);
                }

                return new SampleCollection(
                    name: $name,
                    help: $meta->getHelp(),
                    type: 'gauge',
                    labelNames: $meta->getLabelNames(),
                    samples: $samples,
                );
            })
        ;

        // Counter.
        yield from $databaseSession
            ->executeQuery(
                <<<SQL
                SELECT
                    name, array_agg(json_build_object('value', value, 'labels', labels)) AS samples
                FROM ? sample
                GROUP BY name
                SQL,
                [$this->getTable('counter')]
            )
            ->setHydrator(function (ResultRow $row) use ($schema) {
                $name = $row->get('name', 'string');
                $meta = $schema->getCounter($name, true);

                $samples = [];
                foreach ($row->get('samples', 'string[]') as $data) {
                    $data = \json_decode($data, true);
                    $samples[] = (new Counter($name, $data['labels'], []))->increment((int) $data['value']);
                }

                return new SampleCollection(
                    name: $name,
                    help: $meta->getHelp(),
                    type: 'counter',
                    labelNames: $meta->getLabelNames(),
                    samples: $samples,
                );
            })
        ;

        // Summary.
        $result = $databaseSession->executeQuery(
            <<<SQL
            SELECT
                name, array_agg(json_build_object('value', value, 'labels', labels)) AS samples
            FROM ? sample
            GROUP BY name
            SQL,
            [$this->getTable('summary')]
        );

        foreach ($result as $row) {
            $name = $row->get('name', 'string');
            $meta = $schema->getSummary($name, true);

            // First aggregate all values.
            $values = [];
            foreach ($row->get('samples', 'string[]') as $data) {
                $data = \json_decode($data, true);
                $key = \implode(':', $data['labels']);
                $values[$key][] = (int) $data['value'];
            }

            // Then compute quantiles and build sample list.
            $samples = (function () use ($name, $values, $meta) {
                foreach ($values as $key => $values) {
                    $labels = \explode(':', $key);
                    \sort($values);

                    foreach ($meta->getQuantiles() as $quantile) {
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
                help: $meta->getHelp(),
                type: 'summary',
                labelNames: $meta->getLabelNames(),
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

        $this->checkSchema();
        $databaseSession = $this->getDatabaseSession();

        $counterItems = $gaugeItems = $histogramItems = $summaryItems = [];

        foreach ($samples as $sample) {
            \assert($sample instanceof Sample);
            $name = $sample->name;
            $namespacedName = $schema->getNamespace() . '_' . $name;
            $labelValues = $sample->labelValues;

            if ($sample instanceof Counter) {
                $counterItems[] = [
                    $namespacedName,
                    ExpressionFactory::value($labelValues, 'text[]'),
                    $sample->getValue(),
                    $sample->measuredAt,
                ];
            } else if ($sample instanceof Gauge) {
                $gaugeItems[] = [
                    $namespacedName,
                    ExpressionFactory::value($labelValues, 'text[]'),
                    $sample->getValue(),
                    $sample->measuredAt,
                ];
            } else if ($sample instanceof Histogram) {
                $meta = $schema->getHistogram($name);
                $id = $sample->getUniqueId();

                foreach ($sample->getValues() as $sampleValue) {
                    $bucket = $meta->findBucketFor($sampleValue->value);
                    $itemId = $id . $bucket;

                    if (\array_key_exists($itemId, $histogramItems)) {
                        $histogramItems[$itemId][3] += 1;
                    } else {
                        $histogramItems[$itemId] = [
                            $namespacedName,
                            ExpressionFactory::value($labelValues, 'text[]'),
                            $bucket,
                            1,
                        ];
                    }
                }
            } else if ($sample instanceof Summary) {
                $meta = $schema->getSummary($name);

                foreach ($sample->getValues() as $sampleValue) {
                    $validUntil = $sampleValue->measuredAt->add(new \DateInterval(\sprintf('PT%dS', $meta->getMaxAge())));

                    $summaryItems[] = [
                        $namespacedName,
                        ExpressionFactory::value($labelValues, 'text[]'),
                        $sampleValue->value,
                        $validUntil,
                    ];
                }
            } else {
                \trigger_error(\sprintf("Sample of type '%s' is not supported.", \get_class($sample)), E_USER_WARNING);
            }
        }

        if ($counterItems) {
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
                ]
            );
        }

        if ($histogramItems) {
            $databaseSession->executeStatement(
                <<<SQL
                INSERT INTO ? (
                    "name", "labels", "bucket", "count"
                )
                ?
                ON CONFLICT ("name", "labels", "bucket")
                    DO UPDATE SET
                        "count" = ?."count" + excluded."count"
                SQL,
                [
                    $this->getTable('histogram'),
                    ExpressionFactory::constantTable($histogramItems),
                    $this->getTable('histogram'),
                ]
            );
        }

        if ($summaryItems) {
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
        $this->checkSchema();

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
        $this->checkSchema();

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

    #[\Override]
    protected function doEnsureSchema(): void
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
            'counter' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "value" int NOT NULL DEFAULT 0,
                    "updated" timestamp with time zone NOT NULL,
                    PRIMARY KEY ("name", "labels")
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
            'summary' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "value" float,
                    "valid_until" timestamp with time zone NOT NULL
                )
                SQL,
            'histogram' => <<<SQL
                CREATE TABLE IF NOT EXISTS ? (
                    "name" text NOT NULL,
                    "labels" text[] DEFAULT NULL,
                    "bucket" text,
                    "count" int DEFAULT 0,
                    PRIMARY KEY ("name", "labels", "bucket")
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
}
