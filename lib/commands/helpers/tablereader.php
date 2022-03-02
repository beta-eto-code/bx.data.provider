<?php

namespace BX\Data\Provider\Commands\Helpers;

use Bitrix\Main\Application;
use Bitrix\Main\Db\SqlQueryException;
use CachingIterator;
use EmptyIterator;
use Iterator;

class TableReader implements ReaderEntityInterface
{
    /**
     * @var string
     */
    private $tableName;
    /**
     * @var CachingIterator|null
     */
    private $cachedFields;

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * @ param string $typeDesc
     * @return string
     */
    private static function getType(string $typeDesc)
    {
        $typeDesc = trim(current(explode('(', $typeDesc)));
        switch ($typeDesc) {
            case 'bigint':
            case 'mediumint':
            case 'smallint':
            case 'integer':
            case 'int':
                return TypeList::NUMBER;
            case 'double':
            case 'float':
                return TypeList::FLOAT;
            case 'datetime':
                return TypeList::DATE_TIME;
            case 'date':
                return TypeList::DATE;
            case 'bool':
            case 'boolean':
                return TypeList::BOOLEAN;
            default:
                return TypeList::STRING;
        }
    }

    /**
     * @param string $tableName
     * @return Iterator
     * @throws SqlQueryException
     */
    public static function getFields(string $tableName): Iterator
    {
        $connection = Application::getConnection();
        $query = $connection->query("DESC $tableName");
        while ($fieldDesc = $query->fetch()) {
            $name = $fieldDesc['Field'];
            $typeDesc = $fieldDesc['Type'];
            $isRequired = $fieldDesc['Null'] === 'NO';
            $isAutoIncrement = $fieldDesc['Extra'] === 'auto_increment';
            $isUnique = $fieldDesc['Key'] === 'MUL';
            $type = $isAutoIncrement ? TypeList::AUTOINCREMEТ : static::getType($typeDesc);
            $defaultValue = $fieldDesc['Default'];

            yield new FieldDefinition($name, $type, $defaultValue, $isRequired, $isUnique);
        }

        return new EmptyIterator();
    }

    /**
     * @throws SqlQueryException
     * @return Iterator
     */
    public function getIterator(): Iterator
    {
        if ($this->cachedFields instanceof CachingIterator && $this->cachedFields->count() > 0) {
            $list = $this->cachedFields->getCache();
        } else {
            $this->cachedFields = new CachingIterator(static::getFields($this->tableName), CachingIterator::FULL_CACHE);
            $list = $this->cachedFields;
        }

        foreach ($list as $field) {
            yield $field;
        }

        return new EmptyIterator();
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        $result = [];
        foreach ($this as $field) {
            $result[] = $field->name;
        }

        return $result;
    }
}
