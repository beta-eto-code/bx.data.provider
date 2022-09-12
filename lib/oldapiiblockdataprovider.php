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
    private bool $useWorkflow;

    /**
     * @param string $iblockType
     * @param string $iblockCode
     * @param bool $useWorkflow
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public function __construct(
        string $iblockType,
        string $iblockCode,
        bool $useWorkflow = false
    ) {
        parent::__construct('ID');
        Loader::includeModule('iblock');
        $this->iblock = IblockTable::getList([
            'filter' => [
                '=IBLOCK_TYPE_ID' => $iblockType,
                '=CODE' => $iblockCode,
            ],
            'limit' => 1,
        ])->fetchObject();

        if (empty($this->iblock)) {
            throw new Exception('iblock is not found');
        }

        $this->useWorkflow = $useWorkflow;
    }

    /**
     * @return int
     * @throws SystemException
     */
    private function getIblockId(): int
    {
        if (!($this->iblock instanceof EntityObject)) {
            return 0;
        }

        return (int)$this->iblock->getId();
    }

    /**
     * @param QueryCriteriaInterface|null $query
     * @return Iterator
     * @throws SystemException
     */
    protected function getInternalIterator(QueryCriteriaInterface $query = null): Iterator
    {
        $params = empty($query) ? [] : BxQueryAdapter::init($query)->toArray();
        $params['filter']['IBLOCK_ID'] = $this->getIblockId();
        $select = $params['select'] ?? ['*', 'PROPERTY_*'];
        /**
         * @psalm-suppress UndefinedClass
         */
        $resSelect = CIBlockElement::GetList([], $params['filter'], false, false, $select);
        while ($item = $resSelect->Fetch()) {
            yield $item;
        }

        return new EmptyIterator();
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
            $result['PROPERTY_VALUES'][$k] = $value;
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
            /**
             * @psalm-suppress UndefinedClass
             */
            $isSuccess = (bool)$iblockElementInst->Update($id, $dataForSave, $this->useWorkflow);

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
     * @throws SystemException
     */
    public function getDataCount(QueryCriteriaInterface $query = null): int
    {
        $pkName = $this->getPkName();
        $params = empty($query) ? [] : BxQueryAdapter::init($query)->toArray();
        $params['filter']['IBLOCK_ID'] = $this->getIblockId();

        /**
         * @psalm-suppress UndefinedClass
         */
        return (int) CIBlockElement::GetList(
            [],
            $params['filter'],
            false,
            false,
            [$pkName]
        )->SelectedRowsCount();
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
