<?php

namespace BX\Data\Provider;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Db\SqlQueryException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\SystemException;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\OperationResult;
use Data\Provider\Providers\BaseDataProvider;
use Exception;

class DataManagerDataProvider extends BaseDataProvider
{
    /**
     * @var DataManager
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
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function getDataInternal(QueryCriteriaInterface $query): array
    {
        return $this->dataManagerClass::getList(
            BxQueryAdapter::init($query)->toArray()
        )->fetchAll();
    }

    /**
     * @param array $data
     * @param QueryCriteriaInterface|null $query
     * @return OperationResultInterface|array
     * @throws Exception
     */
    protected function saveInternal(array $data, QueryCriteriaInterface $query = null): OperationResultInterface
    {
        if (empty($query)) {
            $addResult = $this->dataManagerClass::add($data);
            if ($addResult->isSuccess()) {
                return new OperationResult(null, ['data' => $data]);
            }

            return new OperationResult(
                implode(', ', $addResult->getErrorMessages()),
                ['data' => $data]
            );
        }
    }

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->dataManagerClass;
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return int
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getDataCount(QueryCriteriaInterface $query): int
    {
        $params = BxQueryAdapter::init($query)->toArray();
        $params['count_total'] = true;

        return $this->dataManagerClass::getList($params)->getCount();
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return OperationResultInterface
     * @throws Exception
     */
    public function remove(QueryCriteriaInterface $query): OperationResultInterface
    {
        $dataList = $this->getData($query);

        foreach ($dataList as $item) {
            $pkValue = $item[$this->pkName] ?? null;
            if (empty($pkValue)) {
                return new OperationResult('Ошибка удаления данных', ['query' => $query]);
            }

            $this->dataManagerClass::delete($pkValue);
        }

        return new OperationResult(null, ['query' => $query]);
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function startTransaction(): bool
    {
        $connectionName = $this->dataManagerClass::getConnectionName();
        Application::getConnection($connectionName)->startTransaction();
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function commitTransaction(): bool
    {
        $connectionName = $this->dataManagerClass::getConnectionName();
        Application::getConnection($connectionName)->commitTransaction();
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function rollbackTransaction(): bool
    {
        $connectionName = $this->dataManagerClass::getConnectionName();
        Application::getConnection($connectionName)->rollbackTransaction();
    }
}