<?php

namespace BX\Data\Provider;

use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Interfaces\SqlBuilderInterface;
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
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return array
     */
    protected function getDataInternal(QueryCriteriaInterface $query): array
    {
        // TODO: Implement getDataInternal() method.
    }

    /**
     * @param array $data
     * @param QueryCriteriaInterface|null $query
     * @return OperationResultInterface|array
     */
    protected function saveInternal(array $data, QueryCriteriaInterface $query = null): OperationResultInterface
    {
        // TODO: Implement saveInternal() method.
    }

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        // TODO: Implement getSourceName() method.
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return int
     */
    public function getDataCount(QueryCriteriaInterface $query): int
    {
        // TODO: Implement getDataCount() method.
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return OperationResultInterface
     */
    public function remove(QueryCriteriaInterface $query): OperationResultInterface
    {
        // TODO: Implement remove() method.
    }

    /**
     * @return bool
     */
    public function startTransaction(): bool
    {
        // TODO: Implement startTransaction() method.
    }

    /**
     * @return bool
     */
    public function commitTransaction(): bool
    {
        // TODO: Implement commitTransaction() method.
    }

    /**
     * @return bool
     */
    public function rollbackTransaction(): bool
    {
        // TODO: Implement rollbackTransaction() method.
    }
}