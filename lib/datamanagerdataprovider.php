<?php

namespace BX\Data\Provider;

use ArrayObject;
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
use EmptyIterator;
use Exception;
use Iterator;

class DataManagerDataProvider extends BaseDataProvider
{
    /**
     * @var DataManager
     */
    private $dataManagerClass;

    /**
     * @param string|DataManager $className
     * @param string $pkName
     */
    public function __construct($className, string $pkName = 'ID')
    {
        parent::__construct($pkName);
        $this->dataManagerClass = $className;
    }

    /**
     * @param QueryCriteriaInterface|null $query
     * @return Iterator
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function getInternalIterator(QueryCriteriaInterface $query = null): Iterator
    {
        $params = empty($query) ? [] : BxQueryAdapter::init($query)->toArray();
        $resultQuery = $this->dataManagerClass::getList($params);

        while ($item = $resultQuery->fetch()) {
            yield $item;
        }

        return new EmptyIterator();
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
     * @param array|ArrayObject $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     * @throws Exception
     */
    protected function saveInternal(&$data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        if (empty($query)) {
            $dataResult = ['data' => $data];
            $dataForSave = $data instanceof \ArrayObject ? iterator_to_array($data) : $data;
            $addResult = $this->dataManagerClass::add($dataForSave);
            if ($addResult->isSuccess()) {
                $pkValue = $addResult->getId();
                $data[$this->getPkName()] = $pkValue;

                return new OperationResult(null, $dataResult, $pkValue);
            }

            return new OperationResult(
                implode(', ', $addResult->getErrorMessages()),
                $dataResult
            );
        }

        $dataResult = ['query' => $query, 'data' => $data];
        $errorMessage = 'Данные для обновления не найдены';
        $pkName = $this->getPkName();
        if (empty($pkName)) {
            return new OperationResult(
                $errorMessage,
                $dataResult
            );
        }

        $bxQuery = BxQueryAdapter::init($query);
        $pkListForUpdate = $this->getPkValuesByQuery($bxQuery);
        if (empty($pkListForUpdate)) {
            return new OperationResult(
                $errorMessage,
                $dataResult
            );
        }

        $mainResult = null;
        foreach ($pkListForUpdate as $pkValue) {
            $dataForSave = $data instanceof \ArrayObject ? iterator_to_array($data) : $data;
            $bxResult = $this->dataManagerClass::update($pkValue, $dataForSave);
            $updateResult = $bxResult->isSuccess() ?
                new OperationResult('', $dataResult, $pkValue) :
                new OperationResult(implode(', ', $bxResult->getErrorMessages()), $dataResult, $pkValue);

            if ($mainResult instanceof OperationResultInterface) {
                $mainResult->addNext($updateResult);
            } else {
                $mainResult = $updateResult;
            }
        }

        return $mainResult ?? new OperationResult('Данные для сохранения не найдены', $dataResult);
    }

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->dataManagerClass;
    }

    /**
     * @param QueryCriteriaInterface|null $query
     * @return int
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getDataCount(QueryCriteriaInterface $query = null): int
    {
        $params = empty($query) ? [] : BxQueryAdapter::init($query)->toArray();
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

        $mainResult = null;
        $dataResult = ['query' => $query];
        foreach ($pkListForDelete as $pkValue) {
            $bxResult = $this->dataManagerClass::delete($pkValue);
            $updateResult = $bxResult->isSuccess() ?
                new OperationResult('', $dataResult, $pkValue) :
                new OperationResult(implode(', ', $bxResult->getErrorMessages()), $dataResult, $pkValue);

            if ($mainResult instanceof OperationResultInterface) {
                $mainResult->addNext($updateResult);
            } else {
                $mainResult = $updateResult;
            }
        }

        return $mainResult ?? new OperationResult('Данные для удаления не найдены', $dataResult);
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