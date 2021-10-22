<?php

namespace BX\Data\Provider\Commands\Helpers;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserFieldTable;
use CachingIterator;
use EmptyIterator;
use Iterator;
use IteratorAggregate;

class UserFieldReader implements ReaderEntityInterface
{
    /**
     * @var string
     */
    private $entityId;
    /**
     * @var Iterator
     */
    private $cachedFields;

    public function __construct(string $entityId)
    {
        $this->entityId = $entityId;
    }

    /**
     * @param string $typeDesc
     * @return string
     */
    private static function getType(string $typeDesc): string
    {
        switch ($typeDesc) {
            case 'employee':
            case 'integer':
                return TypeList::NUMBER;
            case 'double':
                return TypeList::FLOAT;
            case 'datetime':
                return TypeList::DATE_TIME;
            case 'date':
                return TypeList::DATE;
            case 'boolean':
                return TypeList::BOOLEAN;
            case 'enumeration':
                return TypeList::LIST;
            default:
                return TypeList::STRING;
        }
    }

    /**
     * @return Iterator
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getInternalIterator(): Iterator
    {
        $queryResult = UserFieldTable::getList([
                'filter' => [
                    '=ENTITY_ID' => $this->entityId,
                ],
            ]
        );

        while ($fieldData = $queryResult->fetch()) {
            $settings = json_decode($fieldData['SETTINGS']) ?? [];
            $defaultValue = $settings['DEFAULT_VALUE'] ?? null;

            $name = $fieldData['FIELD_NAME'];
            $isRequired = $fieldData['MANDATORY'] === 'Y';
            $name = $fieldData['FIELD_NAME'];
            $typeDesc = $fieldData['USER_TYPE_ID'];
            $isList = $fieldData['MULTIPLE'] === 'Y';
            $type = $isList ? TypeList::LIST : static::getType($typeDesc);

            if ($type === TypeList::BOOLEAN) {
                yield FieldDefinition::initEnum($name, [0, 1], $isRequired);
                continue;
            }

            yield new FieldDefinition($name, $type, $defaultValue, $isRequired);
        }

        return new EmptyIterator();
    }

    /**
     * @return FieldDefinition[]|Iterator
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getIterator(): Iterator
    {
        if ($this->cachedFields instanceof CachingIterator && $this->cachedFields->count() > 0) {
            $list = $this->cachedFields->getCache();
        } else {
            $this->cachedFields = new CachingIterator($this->getInternalIterator(), CachingIterator::FULL_CACHE);
            $list = $this->cachedFields;
        }

        foreach ($list as $field) {
            yield $field;
        }

        return new EmptyIterator();
    }

    public function getNames(): array
    {
        $result = [];
        foreach ($this as $field) {
            $result[] = $field->name;
        }

        return $result;
    }
}