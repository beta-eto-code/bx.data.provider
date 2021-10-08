<?php

namespace BX\Data\Provider;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\Model\Section;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\SystemException;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Providers\BaseDataProvider;

class SectionIblockDataProvider extends BaseDataProvider
{
    /**
     * @var DataManagerDataProvider
     */
    private $dataManagerProvider;
    /**
     * @var DataManager|string
     */
    private $dataManagerClass;

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

        $iblockId = (int)$iblock['ID'];
        if (empty($iblockId)) {
            throw new Exception('iblock is not found');
        }

        $this->dataManagerClass = Section::compileEntityByIblock($iblockId);
        $this->dataManagerProvider = new DataManagerDataProvider(
            $this->dataManagerClass,
            'ID'
        );
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return array
     */
    protected function getDataInternal(QueryCriteriaInterface $query): array
    {
        return $this->dataManagerProvider->getData($query);
    }

    /**
     * @param array $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     */
    protected function saveInternal(array $data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        return $this->dataManagerProvider->save($data, $query);
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
        return $this->dataManagerProvider->getDataCount($query);
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return OperationResultInterface
     * @throws Exception
     */
    public function remove(QueryCriteriaInterface $query): OperationResultInterface
    {
        return $this->dataManagerProvider->remove($query);
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