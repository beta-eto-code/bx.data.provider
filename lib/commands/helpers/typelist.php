<?php

namespace BX\Data\Provider\Commands\Helpers;

interface TypeList
{
    const AUTOINCREMEТ = 'autoincrement';
    const NUMBER = 'number';
    const EMAIL = 'email';
    const NAME = 'name';
    const LAST_NAME = 'last_name';
    const SECOND_NAME = 'second_name';
    const COMPANY = 'company';
    const PHONE = 'phone';
    const CITY = 'city';
    const ADDRESS = 'address';
    const UUID = 'uuid';
    const PASSWORD = 'password';
    const COUNTRY = 'country';
    const NULL = 'null';
    const STRING = 'string';
    const DATE_TIME = 'datetime';
    const DATE_TIME_STRING = 'datetime_string';
    const DATE = 'date';
    const DATE_STRING = 'date_string';
    const FILE = 'file';
    const FLOAT = 'float';
    const ELEMENT = 'element';
    const SECTION = 'section';
    const LIST = 'list';
    const BOOLEAN = 'bool';
    const ENUM = 'enum';
}