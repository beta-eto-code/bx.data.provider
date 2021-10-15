<?php

namespace BX\Data\Provider;

use ArrayObject;
use Bitrix\Crm\ConfigChecker\Iterator;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\Model\Section;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CIBlockSection;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\OperationResult;
use Data\Provider\QueryCriteria;
use Exception;

class SectionIblockDataProvider extends DataManagerDataProvider
{
    private $iblockId;

    /**
     * @param string $iblockType
     * @param string $iblockCode
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException|LoaderException
     * @throws Exception
     */
    public function __construct(string $iblockType, string $iblockCode)
    {
        parent::__construct('ID');
        Loader::includeModule('iblock');
        $iblock = IblockTable::getList([
            'select' => [
                'ID',
            ],
            'filter' => [
                '=IBLOCK_TYPE_ID' => $iblockType,
                '=CODE' => $iblockCode,
            ],
            'limit' => 1,
        ])->fetchObject();

        $this->iblockId = (int)$iblock['ID'];
        if (empty($this->iblockId)) {
            throw new Exception('iblock is not found');
        }

        $dataManagerClass = Section::compileEntityByIblock($this->iblockId);
        parent::__construct($dataManagerClass);

    }

    public function getIterator(QueryCriteriaInterface $query): \Iterator
    {
        $query = $query ?? new QueryCriteria();
        $query->addCriteria('IBLOCK_ID', CompareRuleInterface::EQUAL, $this->iblockId);

        return parent::getIterator($query);
    }

    public function getData(QueryCriteriaInterface $query = null): array
    {
        $query = $query ?? new QueryCriteria();
        $query->addCriteria('IBLOCK_ID', CompareRuleInterface::EQUAL, $this->iblockId);
        return parent::getData($query);
    }

    public function getDataCount(QueryCriteriaInterface $query = null): int
    {
        $query = $query ?? new QueryCriteria();
        $query->addCriteria('IBLOCK_ID', CompareRuleInterface::EQUAL, $this->iblockId);

        return parent::getDataCount($query);
    }

    /**
     * @param array|ArrayObject $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     * @throws Exception
     */
    protected function saveInternal(&$data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        $dataResult = ['data' => $data];
        $data['IBLOCK_ID'] = $this->iblockId;
        $oSection = new CIBlockSection;
        if (empty($query)) {
            $dataForSave = $data instanceof ArrayObject ? iterator_to_array($data) : $data;
            $id = (int)$oSection->Add($dataForSave);
            if ($id) {
                $data[$this->getPkName()] = $id;
                return new OperationResult(null, $dataResult, $id);
            }

            return new OperationResult(
                $oSection->LAST_ERROR,
                $dataResult
            );
        }

        $dataResult = ['query' => $query, 'data' => $data];
        $query->addCriteria('IBLOCK_ID', CompareRuleInterface::EQUAL, $this->iblockId);
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
            $dataForSave = $data instanceof ArrayObject ? iterator_to_array($data) : $data;
            $isSuccess = (bool)$oSection->Update($pkValue, $dataForSave);
            $saveResult = $isSuccess ?
                new OperationResult('', $dataResult) :
                new OperationResult($oSection->LAST_ERROR, $dataResult);

            if ($mainResult instanceof OperationResultInterface) {
                $mainResult->addNext($saveResult);
            } else {
                $mainResult = $saveResult;
            }
        }

        return $mainResult ?? new OperationResult('Данные для сохранения не найдены', $dataResult);
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return OperationResultInterface
     * @throws Exception
     */
    public function remove(QueryCriteriaInterface $query): OperationResultInterface
    {
        $dataResult = ['query' => $query];
        $query->addCriteria('IBLOCK_ID', CompareRuleInterface::EQUAL, $this->iblockId);
        $bxQuery = BxQueryAdapter::init($query);
        $pkListForDelete = $this->getPkValuesByQuery($bxQuery);
        if (empty($pkListForDelete)) {
            return new OperationResult('Данные для удаления не найдены', $dataResult);
        }

        $mainResult = null;
        foreach ($pkListForDelete as $pkValue) {
            $isSuccess = CIBlockSection::Delete($pkValue);
            $deleteResult = $isSuccess ?
                new OperationResult('', $dataResult) :
                new OperationResult('Ошибка удаления', $dataResult);

            if ($mainResult instanceof OperationResultInterface) {
                $mainResult->addNext($deleteResult);
            } else {
                $mainResult = $deleteResult;
            }
        }

        return $mainResult ?? new OperationResult('Данные для удаления не найдены', $dataResult);
    }
}