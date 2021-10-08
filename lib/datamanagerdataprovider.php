<?php

namespace BX\Data\Provider;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Db\SqlQueryException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\SystemException;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
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
     * @param BxQueryAdapter $bxQuery
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function getPkValuesByQuery(BxQueryAdapter $bxQuery): array
    {
        $pkName = $this->getPkName();

        if ($bxQuery->isEqualPkQuery($pkName)) {
            $result = $bxQuery->getPkValueFromQuery($pkName, CompareRuleInterface::EQUAL);

            return empty($result) ? [] : (array)$result;
        }

        if (empty($pkName)) {
            return [];
        }

        $params = $bxQuery->toArray();
        $params['select'] = [$pkName];
        return array_column(
            $this->dataManagerClass::getList($params)->fetchAll(),
            $pkName
        );
    }

    /**
     * @param array $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     * @throws Exception
     */
    protected function saveInternal(array $data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        if (empty($query)) {
            $addResult = $this->dataManagerClass::add($data);
            if ($addResult->isSuccess()) {
                return new OperationResult(null, ['data' => $data], $addResult->getId());
            }

            return new OperationResult(
                implode(', ', $addResult->getErrorMessages()),
                ['data' => $data]
            );
        }

        $errorMessage = 'Данные для обновления не найдены';
        $pkName = $this->getPkName();
        if (empty($pkName)) {
            return new OperationResult(
                $errorMessage,
                [
                    'data' => $data,
                    'query' => $query
                ]
            );
        }

        $bxQuery = BxQueryAdapter::init($query);
        $pkListForUpdate = $this->getPkValuesByQuery($bxQuery);

        if (empty($pkListForUpdate)) {
            return new OperationResult(
                $errorMessage,
                [
                    'data' => $data,
                    'query' => $query
                ]
            );
        }

        foreach ($pkListForUpdate as $pkValue) {
            $this->dataManagerClass::update($pkValue, $data);
        }

        return new OperationResult(null, ['query' => $query, 'data' => $data]);
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
        $bxQuery = BxQueryAdapter::init($query);
        $pkListForDelete = $this->getPkValuesByQuery($bxQuery);
        if (empty($pkListForDelete)) {
            return new OperationResult('Данные для удаления не найдены', ['query' => $query]);
        }

        foreach ($pkListForDelete as $pkValue) {
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
        return true;
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function commitTransaction(): bool
    {
        $connectionName = $this->dataManagerClass::getConnectionName();
        Application::getConnection($connectionName)->commitTransaction();
        return true;
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function rollbackTransaction(): bool
    {
        $connectionName = $this->dataManagerClass::getConnectionName();
        Application::getConnection($connectionName)->rollbackTransaction();
        return true;
    }
}