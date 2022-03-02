<?php

namespace BX\Data\Provider\Commands\Helpers;

use Bitrix\Main\SystemException;
use EmptyIterator;
use Iterator;

class UserEntityReader extends UserFieldReader
{
    public function __construct()
    {
        parent::__construct('USER');
    }

    private function getTableFields(): Iterator
    {
        yield new FieldDefinition('ID', TypeList::AUTOINCREMEТ);
        yield new FieldDefinition('LOGIN', TypeList::EMAIL, null, true);
        yield new FieldDefinition('PASSWORD', TypeList::PASSWORD);
        yield new FieldDefinition('ACTIVE', TypeList::BOOLEAN);
        yield new FieldDefinition('NAME', TypeList::NAME);
        yield new FieldDefinition('LAST_NAME', TypeList::LAST_NAME);
        yield new FieldDefinition('SECOND_NAME', TypeList::SECOND_NAME);
        yield new FieldDefinition('EMAIL', TypeList::EMAIL);
        yield new FieldDefinition('LAST_LOGIN', TypeList::DATE_TIME);
        yield new FieldDefinition('DATE_REGISTER', TypeList::DATE_TIME);
        yield FieldDefinition::initEnum('LID', ['s1']);

        yield new FieldDefinition('PERSONAL_PROFESSION', TypeList::STRING);
        yield new FieldDefinition('PERSONAL_ICQ', TypeList::STRING);
        yield FieldDefinition::initEnum('PERSONAL_GENDER', ['M', 'F']);
        yield new FieldDefinition('PERSONAL_BIRTHDATE', TypeList::DATE_STRING);
        yield new FieldDefinition('PERSONAL_PHOTO', TypeList::NULL);
        yield new FieldDefinition('PERSONAL_PHONE', TypeList::PHONE);
        yield new FieldDefinition('PERSONAL_FAX', TypeList::PHONE);
        yield new FieldDefinition('PERSONAL_MOBILE', TypeList::PHONE);
        yield new FieldDefinition('PERSONAL_PAGER', TypeList::NULL);
        yield new FieldDefinition('PERSONAL_STREET', TypeList::NULL);
        yield new FieldDefinition('PERSONAL_MAILBOX', TypeList::NULL);
        yield new FieldDefinition('PERSONAL_CITY', TypeList::CITY);
        yield new FieldDefinition('PERSONAL_STATE', TypeList::NULL);
        yield new FieldDefinition('PERSONAL_ZIP', TypeList::NULL);
        yield new FieldDefinition('PERSONAL_COUNTRY', TypeList::COUNTRY);
        yield new FieldDefinition('PERSONAL_NOTES', TypeList::NULL);

        yield new FieldDefinition('WORK_COMPANY', TypeList::COMPANY);
        yield new FieldDefinition('WORK_DEPARTMENT', TypeList::NULL);
        yield new FieldDefinition('WORK_POSITION', TypeList::NULL);
        yield new FieldDefinition('WORK_WWW', TypeList::NULL);
        yield new FieldDefinition('WORK_PHONE', TypeList::PHONE);
        yield new FieldDefinition('WORK_FAX', TypeList::PHONE);
        yield new FieldDefinition('WORK_PAGER', TypeList::NULL);
        yield new FieldDefinition('WORK_STREET', TypeList::NULL);
        yield new FieldDefinition('WORK_MAILBOX', TypeList::NULL);
        yield new FieldDefinition('WORK_CITY', TypeList::CITY);
        yield new FieldDefinition('WORK_STATE', TypeList::NULL);
        yield new FieldDefinition('WORK_ZIP', TypeList::NULL);
        yield new FieldDefinition('WORK_COUNTRY', TypeList::COUNTRY);
        yield new FieldDefinition('WORK_PROFILE', TypeList::NULL);
        yield new FieldDefinition('WORK_LOGO', TypeList::NULL);
        yield new FieldDefinition('WORK_NOTES', TypeList::NULL);

        yield new FieldDefinition('ADMIN_NOTES', TypeList::NULL);
        yield new FieldDefinition('XML_ID', TypeList::UUID);
        yield new FieldDefinition('PERSONAL_BIRTHDAY', TypeList::DATE);
        yield new FieldDefinition('EXTERNAL_AUTH_ID', TypeList::NULL);
        yield new FieldDefinition('LANGUAGE_ID', TypeList::NULL);
        yield new FieldDefinition('BLOCKED', TypeList::NULL);
    }

    /**
     * @throws SystemException
     */
    public function getIterator(): Iterator
    {
        foreach ($this->getTableFields() as $field) {
            yield $field;
        }

        foreach (parent::getIterator() as $field) {
            yield $field;
        }
        return new EmptyIterator();
    }
}
