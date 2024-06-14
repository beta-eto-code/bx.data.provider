<?php

namespace BX\Data\Provider;

use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\SystemException;
use BX\Data\Provider\UserField\UfDataManagerFactory;

class UfDataProvider extends DataManagerDataProvider
{
    /**
     * @throws SqlQueryException
     * @throws SystemException
     */
    public function __construct(string $entityId)
    {
        $dataManager = UfDataManagerFactory::createUfDataManager($entityId);
        parent::__construct($dataManager, 'VALUE_ID');
    }
}
