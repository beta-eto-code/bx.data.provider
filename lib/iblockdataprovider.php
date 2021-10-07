<?php

namespace BX\Data\Provider;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Db\SqlQueryException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\SystemException;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Providers\BaseDataProvider;
use mysql_xdevapi\Exception;

class IblockDataProvider extends BaseDataProvider
{
    /**
     * @var DataManagerDataProvider
     */
    private $dataManagerProvider;
    /**
     * @var DataManager
     */
    private $dataMangerClass;

    /**
     * @param string $iblockType
     * @param string $iblockCode
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function __construct(string $iblockType, string $iblockCode)
    {
        parent::__construct('ID');
        $iblock = IblockTable::getList([
            '=IBLOCK_TYPE_ID' => $iblockType,
            '=CODE' => $iblockCode,
        ])->fetchObject();
        if (empty($iblock)) {
            throw new Exception('iblock is not found');
        }

        $this->dataMangerClass = IblockTable::compileEntity($iblock)->getDataClass();
        $this->dataManagerProvider = new DataManagerDataProvider(
            $this->dataMangerClass,
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
     * @return OperationResultInterface|array
     */
    protected function saveInternal(array $data, QueryCriteriaInterface $query = null): OperationResultInterface
    {
        return $this->dataManagerProvider->save($data, $query);
    }

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->dataMangerClass;
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
     * @throws \Exception
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