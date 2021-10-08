<?php

namespace BX\Data\Provider;

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
        parent::__construct(UserTable::class, 'ID');
    }

    /**
     * @param array $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function saveInternal(array $data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        if (empty($query)) {
            $oUser = new \CUser();
            $id = (int)$oUser->Add($data);
            if ($id === 0) {
                return new OperationResult($oUser->LAST_ERROR, ['data' => $data]);
            }

            return new OperationResult(null, ['data' => $data], $id);
        }

        $bxQuery = BxQueryAdapter::init($query);
        $pkValuesForUpdate = $this->getPkValuesByQuery($bxQuery);

        if (empty($pkValuesForUpdate)) {
            return new OperationResult(
                'Пользователи для обновления не найдены',
                [
                    'query' => $query,
                    'data' => $data
                ]
            );
        }

        $oUser = new \CUser();
        foreach ($pkValuesForUpdate as $pkValue) {
            $oUser->Update($pkValue, $data);
        }

        return new OperationResult(null, ['query' => $query, 'data' => $data]);
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
        $bxQuery = BxQueryAdapter::init($query);
        $pkListForDelete = $this->getPkValuesByQuery($bxQuery);
        if (empty($pkListForDelete)) {
            return new OperationResult('Пользователи для удаления не найдены', ['query' => $query]);
        }

        foreach ($pkListForDelete as $pkValue) {
            \CUser::Delete($pkValue);
        }

        return new OperationResult(null, ['query' => $query]);
    }
}