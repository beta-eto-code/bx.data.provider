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

class OldApiIblockSectionDataProvider extends BaseDataProvider implements IblockDataProviderInterface
{
    /**
     * @var EntityObject|null
     */
    private $iblock;
    /**
     * @var array
     */
    private $defaultFilter = [];

    /**
     * @param EntityObject|null $iblock
     * @throws SystemException
     */
    private function __construct(?EntityObject $iblock = null)
    {
        parent::__construct('ID');
        $this->iblock = $iblock;
        $this->defaultFilter = ['IBLOCK_ID' => $this->getIblockId()];
    }

    /**
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function initByIblock(
        string $iblockType,
        string $iblockCode
    ): OldApiIblockSectionDataProvider {
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

        return new OldApiIblockSectionDataProvider($iblock);
    }

    /**
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public static function initByIblockId(int $iblockId): OldApiIblockSectionDataProvider
    {
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

        return new OldApiIblockSectionDataProvider($iblock);
    }

    /**
     * @param array $defaultFilter
     * @return OldApiIblockSectionDataProvider
     */
    public static function initByDefaultFilter(array $defaultFilter): OldApiIblockSectionDataProvider
    {
        $result = new OldApiIblockSectionDataProvider(null);
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
     * @return string
     * @throws SystemException
     */
    public function getIblockCode(): string
    {
        if (!($this->iblock instanceof EntityObject)) {
            return '';
        }

        return (string)$this->iblock->get('CODE');
    }

    public function getIblockType(): string
    {
        if (!($this->iblock instanceof EntityObject)) {
            return '';
        }

        return (string)$this->iblock->get('IBLOCK_TYPE_ID');
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
     * @param QueryCriteriaInterface|null $query
     * @return Iterator
     */
    protected function getInternalIterator(QueryCriteriaInterface $query = null): Iterator
    {
        $params = empty($query) ? [] : BxQueryAdapter::init($query)->toArray();
        $params['select'] = $params['select'] ?? ['*', 'UF_*'];
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
            yield $item;
        }

        return new EmptyIterator();
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

        $this->updateFilterForOldApi($filter);
        $result = \CIBlockSection::GetList($order, $filter, false, $select, $nav);
        return $result instanceof CIBlockResult ? $result : null;
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
            $result['filter'] = $filter;
        }

        $select = $params['select'] ?? [];
        if (!empty($select)) {
            foreach ($select as $code) {
                $result['select'][] = $code;
            }
        }

        $order = $params['order'] ?? [];
        if (!empty($order)) {
            $result['order'] = $order;
        }

        return $result;
    }

    private function updateFilterForOldApi(array &$filter): void
    {
        if (isset($filter['=CHECK_PERMISSIONS'])) {
            $filter['CHECK_PERMISSIONS'] = $filter['=CHECK_PERMISSIONS'];
            unset($filter['=CHECK_PERMISSIONS']);
        }
        if (isset($filter['=PERMISSIONS_BY'])) {
            $filter['PERMISSIONS_BY'] = $filter['=PERMISSIONS_BY'];
            unset($filter['=PERMISSIONS_BY']);
        }
        if (isset($filter['=IBLOCK_SECTION_ID'])) {
            $filter['=SECTION_ID'] = $filter['=IBLOCK_SECTION_ID'];
            unset($filter['=IBLOCK_SECTION_ID']);
        }
    }

    /**
     * @param array|ArrayObject $data
     * @return array
     * @throws SystemException
     */
    private function prepareDataForSave($data): array
    {
        $result = ['IBLOCK_ID' => $this->getIblockId()];
        foreach ($data as $key => $value) {
            $result[$key] = $value;
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
        $iblockSectionInst = new \CIBlockSection();
        $dataForSave = $this->prepareDataForSave($data);
        $dataResult = ['data' => $dataForSave];
        if (!($query instanceof QueryCriteriaInterface)) {
            /**
             * @psalm-suppress UndefinedClass
             */
            $id = (int)$iblockSectionInst->Add($dataForSave);
            if ($id === 0) {
                /**
                 * @psalm-suppress UndefinedClass
                 */
                return new OperationResult($iblockSectionInst->LAST_ERROR, $dataResult);
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
            $isSuccess = true;
            if (!empty($dataForSave)) {
                /**
                 * @psalm-suppress UndefinedClass
                 */
                $isSuccess = (bool)$iblockSectionInst->Update($id, $dataForSave);
            }

            /**
             * @psalm-suppress UndefinedClass
             */
            $updateResult = new OperationResult(
                $isSuccess ? null : $iblockSectionInst->LAST_ERROR,
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
        $params = empty($query) ? [] : BxQueryAdapter::init($query)->toArray();
        $defaultFilter = $this->defaultFilter;
        if (!empty($defaultFilter)) {
            $params['filter'] = array_merge($params['filter'] ?? [], $defaultFilter);
        }

        $filter = $params['filter'] ?? [];
        $this->updateFilterForOldApi($filter);
        return \CIBlockSection::GetCount($filter);
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
        $resSelect = \CIBlockSection::GetList([], $params['filter'], false, [$pkName]);
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
            $isSuccess = \CIBlockSection::Delete($pkValue);
            $updateResult = $isSuccess ?
                new OperationResult('', $dataResult, $pkValue) :
                new OperationResult("Ошибка удаления раздела #$pkValue", $dataResult, $pkValue);

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
