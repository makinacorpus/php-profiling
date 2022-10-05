<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Store;

use Goat\Driver\DriverFactory;
use Goat\Driver\Error\TableDoesNotExistError;
use Goat\Query\Query;
use Goat\Query\Where;
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

    /**
     * {@inheritdoc}
     */
    public function clear(?\DateTimeInterface $until = null): void
    {
        $query = $this
            ->getRunner()
            ->getQueryBuilder()
            ->delete($this->getTable())
        ;

        if ($until) {
            $query->getWhere()->isLessOrEqual('created', $until);
        }

        $query->perform();
    }

    /**
     * {@inheritdoc}
     */
    public function query(): TraceQuery
    {
        return new class ($this->getRunner(), $this->getTable()) extends TraceQuery
        {
            private Runner $runner;
            private TableExpression $table;

            public function __construct(Runner $runner, TableExpression $table)
            {
                $this->runner = $runner;
                $this->table = $table;
            }

            /**
             * {@inheritdoc}
             */
            public function execute(): TraceQueryResult
            {
                $query = $this
                    ->runner
                    ->getQueryBuilder()
                    ->select($this->table)
                    ->columnExpression('*')
                    ->range($this->limit, $this->offset)
                ;

                $where = $query->getWhere();
                \assert($where instanceof Where);

                if ($this->channels) {
                    $where->expression("channels || ?", new ValueExpression($this->channels, 'text[]'));
                }
                if ($this->from) {
                    $where->isGreaterOrEqual('created', $this->from);
                }
                if ($this->to) {
                    $where->isLessOrEqual('created', $this->to);
                }
                if ($this->orderAsc) {
                    $query->orderBy('created', Query::ORDER_ASC);
                } else {
                    $query->orderBy('created', Query::ORDER_DESC);
                }

                $result = $query->execute();

                return new TraceQueryResult($result, $result->countRows());
            }
        };
    }
}
