<?php

namespace BX\Data\Provider\Commands\Helpers;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CachingIterator;
use EmptyIterator;
use Generator;
use Iterator;
use IteratorAggregate;

class IblockReader implements ReaderEntityInterface
{
    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $code;
    private $cachingProperties;

    public function __construct(string $type, string $code)
    {
        $this->type = $type;
        $this->code = $code;
    }

    /**
     * @param string $type
     * @param string $code
     * @return FieldDefinition[]|Iterator
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getIblockProperties(string $type, string $code): Iterator
    {
        Loader::includeModule('iblock');
        $propertyQuery = PropertyTable::getList([
            'filter' => [
                '=IBLOCK.IBLOCK_TYPE_ID' => $type,
                '=IBLOCK.CODE' => $code,
            ],
        ]);

        while ($property = $propertyQuery->fetch()) {
            $type = TypeList::STRING;
            switch ($property['PROPERTY_TYPE']) {
                case PropertyTable::TYPE_NUMBER:
                    $type = TypeList::NUMBER;
                    break;
                case PropertyTable::TYPE_FILE:
                    $type = TypeList::FILE;
                    break;
                case PropertyTable::TYPE_ELEMENT:
                    $type = TypeList::ELEMENT;
                    break;
                case PropertyTable::TYPE_SECTION:
                    $type = TypeList::SECTION;
                    break;
                case PropertyTable::TYPE_LIST:
                    $type = TypeList::LIST;
                    break;
            }

            yield new FieldDefinition(
                $property['CODE'],
                $type,
                $property['DEFAULT_VALUE'],
                true
            );
        }

        return new EmptyIterator();
    }

    /**
     * @return FieldDefinition[]|Iterator
     */
    public static function getIblockFields(): Iterator
    {
        yield new FieldDefinition(
            'ID',
            TypeList::AUTOINCREMEТ
        );

        yield new FieldDefinition(
            'NAME',
            TypeList::STRING,
            null,
            true
        );

        yield new FieldDefinition(
            'ACTIVE',
            TypeList::BOOLEAN
        );

        yield new FieldDefinition(
            'DATE_CREATE',
            TypeList::DATE_TIME
        );

        yield new FieldDefinition(
            'ACTIVE_FROM',
            TypeList::DATE_TIME
        );

        yield new FieldDefinition(
            'ACTIVE_TO',
            TypeList::DATE_TIME
        );

        yield new FieldDefinition(
            'SORT',
            TypeList::NUMBER
        );

        yield new FieldDefinition(
            'PREVIEW_PICTURE',
            TypeList::NUMBER
        );

        yield new FieldDefinition(
            'PREVIEW_TEXT',
            TypeList::STRING
        );

        yield new FieldDefinition(
            'DETAIL_PICTURE',
            TypeList::NUMBER
        );

        yield new FieldDefinition(
            'DETAIL_TEXT',
            TypeList::STRING
        );

        yield new FieldDefinition(
            'CODE',
            TypeList::STRING
        );

        yield new FieldDefinition(
            'TAGS',
            TypeList::STRING
        );

        yield new FieldDefinition(
            'IBLOCK_SECTION_ID',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'TIMESTAMP_X',
            TypeList::DATE_TIME
        );
    }

    /**
     * @return FieldDefinition[]|EmptyIterator|Iterator
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getIterator(): Iterator
    {
        foreach (static::getIblockFields() as $field) {
            yield $field;
        }

        if ($this->cachingProperties instanceof CachingIterator && $this->cachingProperties->count() > 0) {
            $list = $this->cachingProperties->getCache();
        } else {
            $this->cachingProperties = new CachingIterator(
                static::getIblockProperties($this->type, $this->code),
                CachingIterator::FULL_CACHE
            );
            $list = $this->cachingProperties;
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
