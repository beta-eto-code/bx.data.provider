<?php

namespace BX\Data\Provider;

use ArrayObject;
use Bitrix\Highloadblock\HighloadBlockTable;
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
use Data\Provider\QueryCriteria;
use Exception;
use Iterator;

class HlBlockDataProvider extends BaseDataProvider
{
    /**
     * @var DataManager|string
     */
    private $dataManagerClass;
    /**
     * @var DataManagerDataProvider
     */
    private $dataManagerProvider;

    /**
     * @param array $hlBlockInfo
     * @throws SystemException
     */
    protected function __construct(array $hlBlockInfo)
    {
        parent::__construct('ID');
        $this->dataManagerClass = HighloadBlockTable::compileEntity($hlBlockInfo)->getDataClass();
        $this->dataManagerProvider = new DataManagerDataProvider(
            $this->dataManagerClass,
            'ID'
        );
    }

    /**
     * @param string $tableName
     * @return HlBlockDataProvider
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public static function initByTableName(string $tableName): HlBlockDataProvider
    {
        Loader::includeModule('highloadblock');
        $hlBlockInfo = HighloadBlockTable::getList([
            'filter' => [
                '=TABLE_NAME' => $tableName,
            ],
            'limit' => 1,
        ])->fetch();
        if (empty($hlBlockInfo)) {
            throw new Exception('hlblock is not found');
        }

        return new HlBlockDataProvider($hlBlockInfo);
    }

    /**
     * @param string $hlName
     * @return HlBlockDataProvider
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public static function initByHlName(string $hlName): HlBlockDataProvider
    {
        Loader::includeModule('highloadblock');
        $hlBlockInfo = HighloadBlockTable::getList([
            'filter' => [
                '=NAME' => $hlName,
            ],
            'limit' => 1,
        ])->fetch();
        if (empty($hlBlockInfo)) {
            throw new Exception('hlblock is not found');
        }

        return new HlBlockDataProvider($hlBlockInfo);
    }

    /**
     * @param int $hlBlockId
     * @return HlBlockDataProvider
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public static function initById(int $hlBlockId): HlBlockDataProvider
    {

        Loader::includeModule('highloadblock');
        $hlBlockInfo = HighloadBlockTable::getList([
            'filter' => [
                '=ID' => $hlBlockId,
            ],
            'limit' => 1,
        ])->fetch();
        if (empty($hlBlockInfo)) {
            throw new Exception('hlblock is not found');
        }

        return new HlBlockDataProvider($hlBlockInfo);
    }

    /**
     * @param QueryCriteriaInterface|null $query
     * @return Iterator
     */
    protected function getInternalIterator(QueryCriteriaInterface $query = null): Iterator
    {
        return $this->dataManagerProvider->getIterator($query ?? new QueryCriteria());
    }

    /**
     * @param array|ArrayObject $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     */
    protected function saveInternal(&$data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        return $this->dataManagerProvider->save($data, $query);
    }

    /**
     * @return string
     * @psalm-suppress RedundantConditionGivenDocblockType
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
