<?php

namespace BX\Data\Provider\Commands\Helpers;

interface TypeList
{
    public const AUTOINCREMEТ = 'autoincrement';
    public const NUMBER = 'number';
    public const EMAIL = 'email';
    public const NAME = 'name';
    public const LAST_NAME = 'last_name';
    public const SECOND_NAME = 'second_name';
    public const COMPANY = 'company';
    public const PHONE = 'phone';
    public const CITY = 'city';
    public const ADDRESS = 'address';
    public const UUID = 'uuid';
    public const PASSWORD = 'password';
    public const COUNTRY = 'country';
    public const NULL = 'null';
    public const STRING = 'string';
    public const DATE_TIME = 'datetime';
    public const DATE_TIME_STRING = 'datetime_string';
    public const DATE = 'date';
    public const DATE_STRING = 'date_string';
    public const FILE = 'file';
    public const FLOAT = 'float';
    public const ELEMENT = 'element';
    public const SECTION = 'section';
    public const LIST = 'list';
    public const BOOLEAN = 'bool';
    public const ENUM = 'enum';
}
