<?php

namespace BX\Data\Provider\UserField;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserFieldTable;

class UserFieldEnumTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'b_user_field_enum';
    }


    /**
     * @return array
     * @throws SystemException
     */
    public static function getMap(): array
    {
        return [
            'ID' => new IntegerField('ID', [
                'primary' => true,
                'required' => true,
                'unique' => true,
                'default_value' => null,
            ]),
            'USER_FIELD_ID' => new IntegerField('USER_FIELD_ID', [
                'primary' => false,
                'required' => false,
                'unique' => true,
                'default_value' => null,
            ]),
            'VALUE' => new StringField('VALUE', [
                'primary' => false,
                'required' => true,
                'unique' => false,
                'default_value' => null,
            ]),
            'DEF' => new StringField('DEF', [
                'primary' => false,
                'required' => true,
                'unique' => false,
                'default_value' => 'N',
            ]),
            'SORT' => new IntegerField('SORT', [
                'primary' => false,
                'required' => true,
                'unique' => false,
                'default_value' => '500',
            ]),
            'XML_ID' => new StringField('XML_ID', [
                'primary' => false,
                'required' => true,
                'unique' => false,
                'default_value' => null,
            ]),

            new Reference('USER_FIELD', UserFieldTable::class, [
                '=ref.ID' => 'this.USER_FIELD_ID',
            ]),
        ];
    }
}
