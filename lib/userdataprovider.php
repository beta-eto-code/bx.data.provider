<?php

namespace BX\Data\Provider;

use ArrayObject;
use Bitrix\Crm\ConfigChecker\Iterator;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserTable;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\OperationResult;

class UserDataProvider extends DataManagerDataProvider
{
    public function __construct()
    {
        parent::__construct(UserTable::class);
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
            $dataResult = ['data' => $data];
            $oUser = new \CUser();
            $dataForSave = $data instanceof \ArrayObject ? iterator_to_array($data) : $data;
            $id = (int)$oUser->Add($dataForSave);
            if ($id === 0) {
                return new OperationResult($oUser->LAST_ERROR, $dataResult);
            }

            $data[$this->getPkName() ?? 'ID'] = $id;

            return new OperationResult(null, $dataResult, $id);
        }

        $dataResult = ['query' => $query, 'data' => $data];
        $bxQuery = BxQueryAdapter::init($query);
        $pkValuesForUpdate = $this->getPkValuesByQuery($bxQuery);

        if (empty($pkValuesForUpdate)) {
            return new OperationResult(
                'Пользователи для обновления не найдены',
                $dataResult
            );
        }

        $oUser = new \CUser();
        $mainResult = null;
        foreach ($pkValuesForUpdate as $pkValue) {
            $dataForSave = $data instanceof \ArrayObject ? iterator_to_array($data) : $data;
            $isSuccess = (bool)$oUser->Update($pkValue, $dataForSave);
            $saveResult = $isSuccess ?
                new OperationResult('', $dataResult, $pkValue) :
                new OperationResult($oUser->LAST_ERROR, $dataResult, $pkValue);

            if ($mainResult instanceof OperationResultInterface) {
                $mainResult->addNext($saveResult);
            } else {
                $mainResult = $saveResult;
            }
        }

        return $mainResult instanceof PkOperationResultInterface ?
            $mainResult :
            new OperationResult('Данные для сохранения не найдены', $dataResult);
    }

    /**
     * @param QueryCriteriaInterface $query
     * @return OperationResultInterface
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function remove(QueryCriteriaInterface $query): OperationResultInterface
    {
        $dataResult = ['query' => $query];
        $bxQuery = BxQueryAdapter::init($query);
        $pkListForDelete = $this->getPkValuesByQuery($bxQuery);
        if (empty($pkListForDelete)) {
            return new OperationResult(
                'Пользователи для удаления не найдены',
                $dataResult
            );
        }

        $mainResult = null;
        foreach ($pkListForDelete as $pkValue) {
            $isSuccess = (bool)\CUser::Delete($pkValue);
            $deleteResult = $isSuccess ?
                new OperationResult('', $dataResult, $pkValue) :
                new OperationResult('Ошибка удаления', $dataResult, $pkValue);

            if ($mainResult instanceof OperationResultInterface) {
                $mainResult->addNext($deleteResult);
            } else {
                $mainResult = $deleteResult;
            }
        }

        return $mainResult instanceof OperationResultInterface ?
            $mainResult :
            new OperationResult('Данные для удаления не найдены', $dataResult);
    }
}
