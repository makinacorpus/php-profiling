<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Store;

use MakinaCorpus\Profiling\Helper\WithPidTrait;
use MakinaCorpus\Profiling\TimerTrace;
use MakinaCorpus\Profiling\TraceStore;
use MakinaCorpus\QueryBuilder\BridgeFactory;
use MakinaCorpus\QueryBuilder\DatabaseSession;
use MakinaCorpus\QueryBuilder\Error\Server\TableDoesNotExistError;
use MakinaCorpus\QueryBuilder\Expression\TableName;
use MakinaCorpus\QueryBuilder\ExpressionFactory;
use MakinaCorpus\QueryBuilder\Query\Query;
use MakinaCorpus\QueryBuilder\TableExpression;
use MakinaCorpus\QueryBuilder\Where;

/**
 * Stores profiling information into a database table.
 */
class QueryBuilderTraceStore implements TraceStore
{
    use WithPidTrait;

    private string $databaseUri;
    private string $tableName;
    private ?string $tableSchema = null;
    private ?DatabaseSession $databaseSession = null;
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
    protected function getDatabaseSession(): DatabaseSession
    {
        return $this->databaseSession ??= BridgeFactory::create($this->databaseUri);
    }

    /**
     * Get table expression.
     */
    protected function getTable(): TableName
    {
        return ExpressionFactory::table($this->tableName, $this->tableSchema);
    }

    /**
     * Check table exists, create it if not.
     */
    public function checkTable(): void
    {
        if ($this->tableChecked) {
            return;
        }

        $databaseSession = $this->getDatabaseSession();
        $table = $this->getTable();

        try {
            $databaseSession->executeStatement(
                <<<SQL
                SELECT 1 FROM ?
                SQL,
                [$table]
            );
            $this->tableChecked = true;
        } catch (TableDoesNotExistError $e) {
            $databaseSession->executeStatement(
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

    #[\Override]
    public function store(TimerTrace ...$traces): void
    {
        $this->checkTable();

        if (!$traces) {
            return;
        }

        $query = $this->getDatabaseSession()->insert($this->getTable());

        foreach ($traces as $trace) {
            $query->values([
                'attributes' => ExpressionFactory::value($trace->getAttributes(), 'jsonb'),
                'channels' => ExpressionFactory::value($trace->getChannels(), 'text[]'),
                'created' => new \DateTimeImmutable(),
                'description' => $trace->getDescription(),
                'id' => $trace->getId(),
                'mem' => $trace->getMemoryUsage(),
                'name' => $trace->getAbsoluteName(),
                'pid' => $this->getPid(),
                'relname' => $trace->getName(),
                'time' => $trace->getElapsedTime(),
            ]);
        }

        $query->executeStatement();
    }

    #[\Override]
    public function clear(?\DateTimeInterface $until = null): void
    {
        $query = $this->getDatabaseSession()->delete($this->getTable());

        if ($until) {
            $query->getWhere()->isLessOrEqual('created', $until);
        }

        $query->executeStatement();
    }

    #[\Override]
    public function query(): TraceQuery
    {
        return new class ($this->getDatabaseSession(), $this->getTable()) extends TraceQuery
        {
            private DatabaseSession $databaseSession;
            private TableExpression $table;

            public function __construct(DatabaseSession $databaseSession, TableName $table)
            {
                $this->databaseSession = $databaseSession;
                $this->table = $table;
            }

            #[\Override]
            public function execute(): TraceQueryResult
            {
                $query = $this
                    ->databaseSession
                    ->select($this->table)
                    ->columnRaw('*')
                    ->range($this->limit, $this->offset)
                ;

                $where = $query->getWhere();
                \assert($where instanceof Where);

                if ($this->channels) {
                    $where->raw("channels || ?", ExpressionFactory::value($this->channels, 'text[]'));
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

                $result = $query->executeQuery();

                return new TraceQueryResult($result, $result->rowCount());
            }
        };
    }
}
