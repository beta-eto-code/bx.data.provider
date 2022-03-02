<?php

namespace BX\Data\Provider;

use ArrayObject;
use Bitrix\Main\Application;
use Bitrix\Main\Data\Connection;
use Bitrix\Main\Db\SqlQueryException;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Interfaces\SqlBuilderInterface;
use Data\Provider\Interfaces\SqlRelationProviderInterface;
use Data\Provider\OperationResult;
use Data\Provider\Providers\BaseDataProvider;
use Data\Provider\QueryCriteria;
use EmptyIterator;
use Iterator;

class BxConnectionDataProvider extends BaseDataProvider implements SqlRelationProviderInterface
{
    /**
     * @var string
     */
    protected $tableName;
    /**
     * @var SqlBuilderInterface
     */
    protected $sqlBuilder;
    /**
     * @var Connection|\Bitrix\Main\DB\Connection
     */
    protected $connection;

    public function __construct(
        SqlBuilderInterface $sqlBuilder,
        string $tableName,
        string $connectionName = null,
        string $pkName = null
    ) {
        parent::__construct($pkName);
        $this->tableName = $tableName;
        $this->sqlBuilder = $sqlBuilder;
        $this->connection = Application::getConnection($connectionName ?? '');
    }

    /**
     * @param QueryCriteriaInterface|null $query
     * @return Iterator
     * @throws SqlQueryException
     */
    protected function getInternalIterator(QueryCriteriaInterface $query = null): Iterator
    {
        $query = $query ?? new QueryCriteria();
        $sqlQuery = $this->sqlBuilder->buildSelectQuery($query, $this->tableName, false);
        $queryResult = $this->connection->query((string)$sqlQuery);

        while ($item = $queryResult->fetch()) {
            yield $item;
        }

        return new EmptyIterator();
    }

    /**
     * @param QueryCriteriaInterface|null $query
     * @return int
     * @throws SqlQueryException
     */
    public function getDataCount(QueryCriteriaInterface $query = null): int
    {
        $query = $query ?? new QueryCriteria();
        $whereBlock = $this->sqlBuilder->buildWhereBlock($query, false);
        $sql = "SELECT COUNT(*) as cnt FROM {$this->tableName} {$whereBlock}";
        $queryResult = $this->connection->query($sql);
        $data = $queryResult->fetch();

        return (int)$data['cnt'];
    }

    /**
     * @param array $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     * @throws SqlQueryException
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    protected function saveInternal(&$data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        if (empty($query)) {
            $sqlQuery = $this->sqlBuilder->buildInsertQuery($data, $this->tableName, false);
            $this->connection->queryExecute((string)$sqlQuery);

            return new OperationResult(null, [
                    'data' => $data
                ]);
        }

        $sqlQuery = $this->sqlBuilder->buildUpdateQuery($query, $data, $this->tableName, false);
        $this->connection->queryExecute((string)$sqlQuery);

        return  new OperationResult(
            null,
            [
                    'data' => $data,
                    'query' => $query,
                ]
        );
    }

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->tableName;
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return OperationResultInterface
     * @throws SqlQueryException
     */
    public function remove(QueryCriteriaInterface $query): OperationResultInterface
    {
        $sqlQuery = $this->sqlBuilder->buildDeleteQuery($query, $this->tableName, false);
        $this->connection->queryExecute((string)$sqlQuery);

        return  new OperationResult(null, ['query' => $query]);
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function startTransaction(): bool
    {
        $this->connection->startTransaction();
        return true;
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function commitTransaction(): bool
    {
        $this->connection->commitTransaction();
        return true;
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function rollbackTransaction(): bool
    {
        $this->connection->rollbackTransaction();
        return true;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }
}
