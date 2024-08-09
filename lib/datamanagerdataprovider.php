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
use Data\Provider\QueryCriteria;
use EmptyIterator;
use Exception;
use Iterator;

class DataManagerDataProvider extends BaseDataProvider
{
    protected const PK_LIST_DELIMITER = ',';
    /**
     * @var DataManager
     */
    protected $dataManagerClass;
    /**
     * @var array
     */
    private $defaultFilter = [];

    /**
     * @param mixed $className
     * @param string ...$pkName
     */
    public function __construct($className, string ...$pkName)
    {
        $pkName = implode(static::PK_LIST_DELIMITER, $pkName ?: ['ID']);
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
        if (!empty($this->defaultFilter)) {
            $params['filter'] = array_merge($params['filter'] ?? [], $this->defaultFilter);
        }

        $resultQuery = $this->dataManagerClass::getList($params);
        while ($item = $resultQuery->fetch()) {
            yield $item;
        }

        return new EmptyIterator();
    }

    /**
     * @param array $filter
     * @return void
     */
    public function setDefaultFilter(array $filter)
    {
        $this->defaultFilter = $filter;
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
        $pkList = $this->getPkList();
        $params = $bxQuery->toArray();
        $params['select'] = $pkList;
        if (count($pkList) === 1) {
            $pkName = current($pkList);
            if (empty($pkName)) {
                return [];
            }

            if ($bxQuery->isEqualPkQuery($pkName)) {
                $result = $bxQuery->getPkValueFromQuery($pkName, CompareRuleInterface::EQUAL);

                return empty($result) ? [] : (array)$result;
            }

            return array_column(
                $this->dataManagerClass::getList($params)->fetchAll(),
                $pkName
            );
        } elseif (count($pkList) > 1) {
            $elements = $this->dataManagerClass::getList($params)->fetchAll();
            $pkValues = [];
            foreach ($elements as $element) {
                $pkValue = [];
                foreach ($element as $key => $value) {
                    $pkValue[$key] = $value;
                }
                $pkValues[] = $pkValue;
            }

            return $pkValues;
        }

        return [];
    }

    public function getPkList(): array
    {
        $pkName = $this->getPkName();
        return empty($pkName) ? [] : explode(static::PK_LIST_DELIMITER, $pkName);
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
                if (is_array($pkValue)) {
                    foreach ($pkValue as $name => $value) {
                        $data[$name] = $value;
                    }
                } else {
                    $data[$this->getPkName() ?? 'ID'] = $pkValue;
                }

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

        return $mainResult instanceof PkOperationResultInterface ?
            $mainResult :
            new OperationResult('Данные для сохранения не найдены', $dataResult);
    }

    /**
     * @return string
     * @psalm-suppress RedundantConditionGivenDocblockType,DocblockTypeContradiction
     */
    public function getSourceName(): string
    {
        if (is_string($this->dataManagerClass)) {
            return $this->dataManagerClass;
        }

        if (is_object($this->dataManagerClass)) {
            return get_class($this->dataManagerClass);
        }

        return '';
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

        return $mainResult instanceof OperationResultInterface ?
            $mainResult :
            new OperationResult('Данные для удаления не найдены', $dataResult);
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

    /**
     * @param array|ArrayObject $data
     * @return void
     */
    public function clearPk(&$data)
    {
        foreach ($this->getPkList() as $pkName) {
            if (!empty($pkName)) {
                unset($data[$pkName]);
            }
        }
    }

    /**
     * @param array|ArrayObject $data
     * @return mixed
     */
    public function getPkValue($data)
    {
        if (empty($this->getPkName())) {
            return null;
        }

        $pkValues = [];
        foreach ($this->getPkList() as $pkName) {
            if (!empty($pkName) && isset($data[$pkName])) {
                $pkValues[$pkName] = $data[$pkName];
            }
        }
        if (count($pkValues) === 1) {
            return current($pkValues);
        } elseif (count($pkValues) > 1) {
            return $pkValues;
        }

        return null;
    }

    /**
     * @param array|ArrayObject $data
     *
     * @return QueryCriteria|null
     */
    public function createPkQuery($data): ?QueryCriteriaInterface
    {
        $pkName = $this->getPkName();
        if (empty($pkName)) {
            return null;
        }

        $pkValue = $this->getPkValue($data);
        if (empty($pkValue)) {
            return null;
        }

        $query = new QueryCriteria();
        if (is_array($pkValue)) {
            foreach ($pkValue as $name => $value) {
                $query->addCriteria($name, CompareRuleInterface::EQUAL, $value);
            }
        } else {
            $query->addCriteria($pkName, CompareRuleInterface::EQUAL, $pkValue);
        }

        return $query;
    }
}
