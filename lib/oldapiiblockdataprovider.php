<?php

namespace BX\Data\Provider;

use ArrayObject;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Objectify\EntityObject;
use Bitrix\Main\SystemException;
use CIBlockElement;
use CIBlockResult;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\OperationResult;
use Data\Provider\Providers\BaseDataProvider;
use EmptyIterator;
use Exception;
use Iterator;

class OldApiIblockDataProvider extends BaseDataProvider
{
    /**
     * @var EntityObject|null
     */
    private $iblock;
    /**
     * @var bool
     */
    private $useWorkflow = false;
    /**
     * @var array
     */
    private $defaultFilter = [];
    /**
     * @var null|callable(CIBlockElement $el, int $limit, int $offset): string
     */
    private $sqlBuilder;
    /**
     * @var bool
     */
    private $enableComplexProperty;
    private bool $partialPropertyUpdate;

    /**
     * @param EntityObject|null $iblock
     * @param bool $useWorkflow
     * @param bool $partialPropertyUpdate
     * @param bool $enableComplexProperty
     * @throws SystemException
     */
    private function __construct(
        ?EntityObject $iblock = null,
        bool $useWorkflow = false,
        bool $partialPropertyUpdate = false,
        bool $enableComplexProperty = true
    ) {
        parent::__construct('ID');
        $this->iblock = $iblock;
        $this->useWorkflow = $useWorkflow;
        $this->defaultFilter = ['IBLOCK_ID' => $this->getIblockId()];
        $this->partialPropertyUpdate = $partialPropertyUpdate;
        $this->enableComplexProperty = $enableComplexProperty;
    }

    /**
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function initByIblock(
        string $iblockType,
        string $iblockCode,
        bool $useWorkflow = false,
        bool $partialPropertyUpdate = false,
        bool $enableComplexProperty = true
    ): OldApiIblockDataProvider {
        Loader::includeModule('iblock');
        $iblock = IblockTable::getList([
            'filter' => [
                '=IBLOCK_TYPE_ID' => $iblockType,
                '=CODE' => $iblockCode,
            ],
            'limit' => 1,
        ])->fetchObject();

        if (empty($iblock)) {
            throw new Exception('iblock is not found');
        }

        return new OldApiIblockDataProvider($iblock, $useWorkflow, $partialPropertyUpdate, $enableComplexProperty);
    }

    /**
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public static function initByIblockId(
        int $iblockId,
        bool $partialPropertyUpdate = false,
        bool $enableComplexProperty = true
    ): OldApiIblockDataProvider {
        Loader::includeModule('iblock');
        $iblock = IblockTable::getList([
            'filter' => [
                '=ID' => $iblockId
            ],
            'limit' => 1,
        ])->fetchObject();

        if (empty($iblock)) {
            throw new Exception('iblock is not found');
        }

        return new OldApiIblockDataProvider($iblock, false, $partialPropertyUpdate, $enableComplexProperty);
    }

    /**
     * @param array $defaultFilter
     * @param bool $useWorkflow
     * @return OldApiIblockDataProvider
     * @throws SystemException
     */
    public static function initByDefaultFilter(
        array $defaultFilter,
        bool $useWorkflow = false
    ): OldApiIblockDataProvider {
        $result = new OldApiIblockDataProvider(null, $useWorkflow);
        $result->setDefaultFilter($defaultFilter);

        return $result;
    }

    /**
     * @return int
     * @throws SystemException
     */
    public function getIblockId(): int
    {
        if (!($this->iblock instanceof EntityObject)) {
            return 0;
        }

        return (int)$this->iblock->getId();
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
     * @param callable(CIBlockElement $el, int $limit, int $offset): string $fnBuild
     * @return void
     */
    public function setSqlBuilder(callable $fnBuild)
    {
        $this->sqlBuilder = $fnBuild;
    }

    /**
     * @param QueryCriteriaInterface|null $query
     * @return Iterator
     */
    protected function getInternalIterator(QueryCriteriaInterface $query = null): Iterator
    {
        $params = empty($query) ? [] : BxQueryAdapter::init($query)->toArray();
        $params['select'] = $params['select'] ?? ['*', 'PROPERTY_*'];
        $defaultFilter = $this->defaultFilter;
        if (!empty($defaultFilter)) {
            $params['filter'] = array_merge($params['filter'] ?? [], $defaultFilter);
        }

        /**
         * @psalm-suppress UndefinedClass
         */
        $resSelect = $this->internalGetList($params);
        if (empty($resSelect)) {
            return new EmptyIterator();
        }

        while ($item = $resSelect->Fetch()) {
            yield $this->prepareResultItem($item);
        }

        return new EmptyIterator();
    }

    /**
     * @param array $item
     * @return array
     */
    private function prepareResultItem(array $item): array
    {
        $result = [];
        foreach ($item as $key => $value) {
            $key = str_replace('PROPERTY_', '', $key);
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array $params
     * @return CIBlockResult|null
     */
    private function internalGetList(array $params): ?CIBlockResult
    {
        $params = $this->prepareParams($params);
        $filter = $params['filter'] ?? [];
        $select = $params['select'] ?? [];
        $order = $params['order'] ?? [];
        $nav = false;
        if (isset($params['limit']) && (int)$params['limit'] > 0) {
            $nav = [
                'nPageSize' => (int)$params['limit'],
                'iNumPage' => (int)$params['page'],
            ];
        }

        if (empty($this->sqlBuilder)) {
            $result = CIBlockElement::GetList($order, $filter, false, $nav, $select);
            return $result instanceof CIBlockResult ? $result : null;
        }

        $el = new CIBlockElement();
        $el->prepareSql($select, $filter, false, $order);
        $sql = ($this->sqlBuilder)($el, (int)$params['limit'], (int)$params['offset']);
        if (empty($sql)) {
            return null;
        }

        global $DB;
        $res = $DB->Query($sql, false, "FILE: " . __FILE__ . "<br> LINE: " . __LINE__);
        $res = new CIBlockResult($res);
        $res->SetIBlockTag($el->arFilterIBlocks);
        $res->arIBlockMultProps = $el->arIBlockMultProps;
        $res->arIBlockConvProps = $el->arIBlockConvProps;
        $res->arIBlockAllProps  = $el->arIBlockAllProps;
        $res->arIBlockNumProps = $el->arIBlockNumProps;
        $res->arIBlockLongProps = $el->arIBlockLongProps;

        return $res;
    }

    public function prepareParams(array $params): array
    {
        $page = 1;
        $limit = (int)$params['limit'];
        $offset = (int)$params['offset'];
        if ($limit > 0 && $offset > 0) {
            $page = ((int)($offset / $limit)) + 1;
        }

        $result = [
            'limit' => $limit,
            'offset' => $offset,
            'page' => $page,
        ];

        $filter = $params['filter'] ?? [];
        if (!empty($filter)) {
            foreach ($filter as $code => $value) {
                $code = $this->prepareCode($code, false);
                $result['filter'][$code] = $value;
            }
        }

        $select = $params['select'] ?? [];
        if (!empty($select)) {
            foreach ($select as $code) {
                $code = $this->prepareCode($code, false);
                $result['select'][] = $code;
            }
        }

        $order = $params['order'] ?? [];
        if (!empty($order)) {
            foreach ($order as $code => $direction) {
                $code = $this->prepareCode($code, false);
                $result['order'][$code] = $direction;
            }
        }

        return $result;
    }

    /**
     * @param string $code
     * @param bool $withValue
     * @return string
     */
    private function prepareCode(string $code, bool $withValue): string
    {
        $search = '_VALUE';
        if (strpos($code, $search) === false) {
            return $code;
        }

        $parts = [];
        $isSuccess = (bool)preg_match(
            "/([\=\<\>\!\~]*)(PROPERTY_|)(\S*)$search/",
            $code,
            $parts
        );

        if (!$isSuccess || count($parts) < 4) {
            return $code;
        }

        $operation = $parts[1];
        $propertyName = $parts[3];

        return "{$operation}PROPERTY_{$propertyName}" . ($withValue ? $search : '');
    }

    /**
     * @param array|ArrayObject $data
     * @return array
     * @throws SystemException
     */
    private function prepareDataForSave($data): array
    {
        $searchKey = '_VALUE';
        $result = ['IBLOCK_ID' => $this->getIblockId()];
        foreach ($data as $key => $value) {
            if (strpos($key, $searchKey) === false) {
                $result[$key] = $value;
                continue;
            }

            $k = str_replace($searchKey, '', $key);
            $result['PROPERTY_VALUES'][$k] = $this->enableComplexProperty ? $this->getUpdatedProperty($value) : $value;
        }

        return $result;
    }

    /**
     * @param array|ArrayObject $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     * @throws SystemException
     * @psalm-suppress InvalidReturnType
     */
    protected function saveInternal(&$data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        $pkName = $this->getPkName();

        /**
         * @psalm-suppress UndefinedClass
         */
        $iblockElementInst = new CIBlockElement();
        $dataForSave = $this->prepareDataForSave($data);
        $dataResult = ['data' => $dataForSave];
        if (!($query instanceof QueryCriteriaInterface)) {
            /**
             * @psalm-suppress UndefinedClass
             */
            $id = (int) $iblockElementInst->Add($dataForSave, $this->useWorkflow, $this->useWorkflow);
            if ($id === 0) {
                /**
                 * @psalm-suppress UndefinedClass
                 */
                return new OperationResult($iblockElementInst->LAST_ERROR, $dataResult);
            }

            /**
             * @psalm-suppress PossiblyNullArrayOffset
             */
            $data[$pkName] = $id;
            $data['IBLOCK_ID'] = $this->getIblockId();
            return new OperationResult(null, $dataResult, $id);
        }

        $bxQuery = BxQueryAdapter::init($query);
        $pkListForSave = $this->getPkValuesByQuery($bxQuery);
        if (empty($pkListForSave)) {
            return new OperationResult(
                'Данные для обновления не найдены',
                $dataResult
            );
        }

        $mainResult = null;
        foreach ($pkListForSave as $id) {
            $properties = $dataForSave['PROPERTY_VALUES'];
            $fields = $dataForSave;
            unset($fields['PROPERTY_VALUES']);

            $isSuccess = true;
            if (!empty($fields)) {
                /**
                 * @psalm-suppress UndefinedClass
                 */
                $isSuccess = (bool)$iblockElementInst->Update($id, $fields, $this->useWorkflow);
            }

            if ($this->partialPropertyUpdate) {
                $iblockElementInst::SetPropertyValuesEx($id, $this->getIblockId(), $properties);
            } else {
                $iblockElementInst::SetPropertyValues($id, $this->getIblockId(), $properties);
            }

            /**
             * @psalm-suppress UndefinedClass
             */
            $updateResult = new OperationResult(
                $isSuccess ? null : $iblockElementInst->LAST_ERROR,
                $dataResult,
                $id
            );

            if ($mainResult instanceof OperationResultInterface) {
                $mainResult->addNext($updateResult);
            } else {
                $mainResult = $updateResult;
            }
        }

        $data['IBLOCK_ID'] = $this->getIblockId();

        return $mainResult instanceof PkOperationResultInterface ?
            $mainResult :
            new OperationResult('Данные для сохранения не найдены', $dataResult);
    }

    /**
     * @param mixed $property
     * @return mixed
     */
    private function getUpdatedProperty($property)
    {
        if (!is_array($property)) {
            return $property;
        }

        if ($this->hasStringKeys($property) && !array_key_exists('VALUE', $property)) {
            return ['VALUE' => $property, 'DESCRIPTION' => $property['DESCRIPTION'] ?: ''];
        } elseif (!$this->hasStringKeys($property)) {
            foreach ($property as $k => $v) {
                $property[$k] = $this->getUpdatedProperty($v);
            }
        }
        return $property;
    }

    private function hasStringKeys(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     * @throws SystemException
     */
    public function getSourceName(): string
    {
        return (string)$this->getIblockId();
    }

    /**
     * @param QueryCriteriaInterface|null $query
     * @return int
     */
    public function getDataCount(QueryCriteriaInterface $query = null): int
    {
        $pkName = $this->getPkName();
        $params = empty($query) ? [] : BxQueryAdapter::init($query)->toArray();
        $defaultFilter = $this->defaultFilter;
        if (!empty($defaultFilter)) {
            $params['filter'] = array_merge($params['filter'] ?? [], $defaultFilter);
        }

        $filter = $params['filter'] ?? [];
        $order = $params['order'] ?? [];
        $el = new CIBlockElement();
        $el->prepareSql([$pkName], $filter, false, $order);
        $sql = "SELECT COUNT('*') as count FROM $el->sFrom WHERE 1=1 $el->sWhere";
        $sql = str_replace(
            "AND (((BE.WF_STATUS_ID=1 AND BE.WF_PARENT_ELEMENT_ID IS NULL)))",
            "",
            $sql
        );

        global $DB;
        $res = $DB->Query($sql);
        $res = $res->Fetch();
        return (int)$res["count"];
    }

    /**
     * @param BxQueryAdapter $bxQuery
     * @return array
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
        $params['filter']['IBLOCK_ID'] = $this->getIblockId();

        /**
         * @psalm-suppress UndefinedClass
         */
        $resSelect = CIBlockElement::GetList([], $params['filter'], false, false, [$pkName]);
        $result = [];
        while ($item = $resSelect->Fetch()) {
            $result[] = $item[$pkName];
        }

        return $result;
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return OperationResultInterface
     * @throws SystemException
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
            /**
             * @psalm-suppress UndefinedClass
             */
            $isSuccess = CIBlockElement::Delete($pkValue);
            $updateResult = $isSuccess ?
                new OperationResult('', $dataResult, $pkValue) :
                new OperationResult("Ошибка удаление элемента #$pkValue", $dataResult, $pkValue);

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
        Application::getConnection()->startTransaction();
        return true;
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function commitTransaction(): bool
    {
        Application::getConnection()->commitTransaction();
        return true;
    }

    /**
     * @return bool
     * @throws SqlQueryException
     */
    public function rollbackTransaction(): bool
    {
        Application::getConnection()->rollbackTransaction();
        return true;
    }
}
