<?php

namespace BX\Data\Provider;

use ArrayObject;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\ORM\ElementEntity;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Objectify\EntityObject;
use Bitrix\Main\ORM\Objectify\State;
use Bitrix\Main\SystemException;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\OperationResult;
use Exception;

class IblockDataProvider extends DataManagerDataProvider
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
     * @var ElementEntity|false
     */
    private $elementEntity;

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

        $this->elementEntity = IblockTable::compileEntity($iblock);
        parent::__construct($this->elementEntity->getDataClass());
    }

    /**
     * @param $data
     * @param int|null $pk
     * @return EntityObject
     * @throws ArgumentException
     * @throws SystemException
     */
    private function initItem($data, int $pk = null): EntityObject
    {
        $item = $this->elementEntity->createObject();
        if ((int)$pk > 0 ) {
            $item->setId($pk);
            $item->sysChangeState(State::CHANGED);
        }

        $search = '_VALUE';
        foreach ($data as $key => $value) {
            if (strpos($key, $search)) {
                $key = str_replace($search, '', $key);
            }

            if ($key !== 'ID' && !is_null($value)) {
                $item->set($key, $value ?? '');
            }
        }

        return $item;
    }

    /**
     * @param array|ArrayObject $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function saveInternal(&$data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        if (empty($query)) {
            $item = $this->initItem($data);
            $addResult = $item->save();
            if ($addResult->isSuccess()) {
                $pkValue = $addResult->getId();
                $data[$this->getPkName()] = $pkValue;

                return new OperationResult(null, ['data' => $data], $pkValue);
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
            $item = $this->initItem($data, (int)$pkValue);
            $item->save();
        }

        return new OperationResult(null, ['query' => $query, 'data' => $data]);
    }
}