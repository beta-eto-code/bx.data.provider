<?php

namespace BX\Data\Provider\UserField;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields\ArrayField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use Bitrix\Tasks\Util\Entity\DateTimeField;
use CUserTypeManager;

class MultiUserField extends ArrayField
{
    private string $fieldType;
    private int $fieldId;
    private Entity $utmEntity;

    public function __construct(
        string $fieldName,
        string $fieldType,
        int $fieldId,
        Entity $utmEntity,
        $parameters = []
    ) {
        $this->fieldType = $fieldType;
        $this->fieldId = $fieldId;
        $this->utmEntity = $utmEntity;
        parent::__construct($fieldName, $parameters);
        $this->configureSerializationPhp();
    }

    public function getFieldNameForFilter(): string
    {
        $utmFieldName = $this->getUrmFieldName();
        $fieldTypesMap = UfDataManagerFactory::getFieldTypesMap();
        switch ($fieldTypesMap[$this->fieldType] ?? '') {
            case CUserTypeManager::BASE_TYPE_INT:
            case CUserTypeManager::BASE_TYPE_FILE:
                return $utmFieldName . '.' . 'VALUE_INT';
            case CUserTypeManager::BASE_TYPE_DOUBLE:
                return $utmFieldName . '.' . 'VALUE_DOUBLE';
            case CUserTypeManager::BASE_TYPE_DATETIME:
                return $utmFieldName . '.' . 'VALUE_DATE';
            default:
                return $utmFieldName . '.' . 'VALUE';
        }
    }

    public function getUrmFieldName(): string
    {
        return $this->name . '_UTM';
    }

    /**
     * @throws SystemException
     * @throws ArgumentException
     */
    public function getOrCreateReferenceField(): ReferenceField
    {
        if (empty($this->referenceField)) {
            $this->referenceField = new ReferenceField(
                $this->getUrmFieldName(),
                $this->utmEntity,
                Join::on('this.VALUE_ID', 'ref.VALUE_ID')->where('ref.FIELD_ID', '=', $this->fieldId)
            );
        }

        return $this->referenceField;
    }
}
