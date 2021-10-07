<?php

namespace BX\Data\Provider;

use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Providers\BaseDataProvider;

class DataManagerDataProvider extends BaseDataProvider
{
    /**
     * @var string
     */
    private $dataManagerClass;

    public function __construct(string $className, string $pkName = 'ID')
    {
        parent::__construct($pkName);
        $this->dataManagerClass = $className;
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