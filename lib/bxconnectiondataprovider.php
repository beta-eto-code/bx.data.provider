<?php

namespace BX\Data\Provider;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Connection;
use Bitrix\Main\Db\SqlQueryException;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Interfaces\SqlBuilderInterface;
use Data\Provider\OperationResult;
use Data\Provider\Providers\BaseDataProvider;

class BxConnectionDataProvider extends BaseDataProvider
{
    /**
     * @var string
     */
    private $tableName;
    /**
     * @var SqlBuilderInterface
     */
    private $sqlBuilder;
    /**
     * @var Connection|\Bitrix\Main\DB\Connection
     */
    private $connection;

    public function __construct(
        SqlBuilderInterface $sqlBuilder,
        string $tableName,
        string $connectionName = null,
        string $pkName = null
    )
    {
        parent::__construct($pkName);
        $this->tableName = $tableName;
        $this->sqlBuilder = $sqlBuilder;
        $this->connection = Application::getConnection($connectionName ?? '');
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return array
     * @throws SqlQueryException
     */
    protected function getDataInternal(QueryCriteriaInterface $query): array
    {
        $sqlQuery = $this->sqlBuilder->buildSelectQuery($query, $this->tableName, false);
        $queryResult = $this->connection->query((string)$sqlQuery);

        return $queryResult->fetchAll();
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return int
     */
    public function getDataCount(QueryCriteriaInterface $query): int
    {
        $whereBlock = $this->sqlBuilder->buildWhereBlock($query, false);
        $sql = "SELECT COUNT(*) as cnt FROM {$this->tableName} {$whereBlock}";
        $queryResult = $this->connection->query((string)$sql);
        $data = $queryResult->fetch();

        return (int)$data['cnt'];
    }

    /**
     * @param array $data
     * @param QueryCriteriaInterface|null $query
     * @return OperationResultInterface|array
     * @throws SqlQueryException
     */
    protected function saveInternal(array $data, QueryCriteriaInterface $query = null): OperationResultInterface
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
}