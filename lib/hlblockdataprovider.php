<?php

namespace BX\Data\Provider;

use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Providers\BaseDataProvider;

class HlBlockDataProvider extends BaseDataProvider
{
    private function __construct(array $hlBlockInfo)
    {
        parent::__construct('ID');
    }

    public static function initByTableName(string $tableName): HlBlockDataProvider
    {

    }

    public static function initByHlName(string $hlName): HlBlockDataProvider
    {

    }

    public static function initById(int $hlBlockId): HlBlockDataProvider
    {

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