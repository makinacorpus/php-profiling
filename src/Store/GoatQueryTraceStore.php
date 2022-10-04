<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Store;

use Goat\Driver\DriverFactory;
use Goat\Driver\Error\TableDoesNotExistError;
use Goat\Query\Expression\TableExpression;
use Goat\Query\Expression\ValueExpression;
use Goat\Runner\Runner;
use MakinaCorpus\Profiling\ProfilerTrace;
use MakinaCorpus\Profiling\TraceStore;
use MakinaCorpus\Profiling\Helper\WithPidTrait;

/**
 * Stores profiling information into a database table.
 */
class GoatQueryTraceStore implements TraceStore
{
    use WithPidTrait;

    private string $databaseUri;
    private string $tableName;
    private ?string $tableSchema = null;
    private ?Runner $runner = null;
    private bool $tableChecked = false;
    private ?int $pid = null;

    public function __construct(string $databaseUri, ?string $tableName = null, ?string $tableSchema = null)
    {
        $this->databaseUri = $databaseUri;
        $this->tableName = $tableName ?? 'profiling_trace';
        $this->tableSchema = $tableSchema;
    }

    /**
     * Get database connection.
     */
    protected function getRunner(): Runner
    {
        if (!$this->runner) {
            $this->runner = DriverFactory::fromUri($this->databaseUri)->getRunner();
        }
        return $this->runner;
    }

    /**
     * Get table expression.
     */
    protected function getTable(): TableExpression
    {
        return new TableExpression($this->tableName, $this->tableSchema);
    }

    /**
     * Check table exists, create it if not.
     */
    public function checkTable(): void
    {
        if ($this->tableChecked) {
            return;
        }

        $runner = $this->getRunner();
        $table = $this->getTable();

        try {
            $runner->execute(
                <<<SQL
                SELECT 1 FROM ?
                SQL,
                [$table]
            );
            $this->tableChecked = true;
        } catch (TableDoesNotExistError $e) {
            $runner->execute(
                <<<SQL
                CREATE TABLE ? (
                    "pid" bigint DEFAULT NULL,
                    "created" timestamp with time zone NOT NULL DEFAULT current_timestamp,
                    "id" text NOT NULL,
                    "name" text NOT NULL,
                    "relname" text NOT NULL,
                    "mem" bigint NOT NULL,
                    "time" decimal(10,3) NOT NULL,
                    "description" text DEFAULT NULL,
                    "channels" text[] NOT NULL DEFAULT '{}',
                    "attributes" jsonb DEFAULT '{}'
                )
                SQL,
                [$table]
            );
            $this->tableChecked = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function store(ProfilerTrace ...$traces): void
    {
        $this->checkTable();

        $query = $this
            ->getRunner()
            ->getQueryBuilder()
            ->insert($this->getTable())
        ;

        foreach ($traces as $trace) {
            $query->values([
                "pid" => $this->getPid(),
                "created" => new \DateTimeImmutable(),
                "id" => $trace->getId(),
                "name" => $trace->getAbsoluteName(),
                "relname" => $trace->getName(),
                "mem" => $trace->getMemoryUsage(),
                "time" => $trace->getElapsedTime(),
                "description" => $trace->getDescription(),
                "channels" => new ValueExpression($trace->getChannels(), 'string[]'),
                "attributes" => new ValueExpression($trace->getAttributes(), 'jsonb'),
            ]);
        }

        $query->perform();
    }
}
