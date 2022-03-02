<?php

namespace BX\Data\Provider\Commands\Helpers;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bx\Model\Gen\Readers\IblockReader;
use EmptyIterator;
use Iterator;

class IblockSectionReader implements ReaderEntityInterface
{
    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $code;
    /**
     * @var UserFieldReader|null
     */
    private $ufReader;
    /**
     * @var int|null
     */
    private $iblockId;

    public function __construct(string $type, string $code)
    {
        $this->type = $type;
        $this->code = $code;
    }

    /**
     * @return int
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getIblockId(): int
    {
        if ($this->iblockId !== null) {
            return $this->iblockId;
        }

        Loader::includeModule('iblock');
        $iblockData = IblockTable::getRow([
            'select' => [
                'ID',
            ],
            'filter' => [
                '=IBLOCK_TYPE_ID' => $this->type,
                '=CODE' => $this->code,
            ],
        ]);

        return $this->iblockId = (int)$iblockData['ID'];
    }

    private function getSectionsFields(): Iterator
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
            'GLOBAL_ACTIVE',
            TypeList::BOOLEAN
        );

        yield new FieldDefinition(
            'MODIFIED_BY',
            TypeList::NUMBER
        );

        yield new FieldDefinition(
            'CREATED_BY',
            TypeList::NUMBER
        );

        yield new FieldDefinition(
            'SORT',
            TypeList::NUMBER
        );

        yield new FieldDefinition(
            'PICTURE',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'DETAIL_PICTURE',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'SOCNET_GROUP_ID',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'CODE',
            TypeList::STRING
        );

        yield new FieldDefinition(
            'DESCRIPTION',
            TypeList::STRING
        );

        yield new FieldDefinition(
            'DESCRIPTION_TYPE',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'XML_ID',
            TypeList::STRING
        );

        yield new FieldDefinition(
            'TMP_ID',
            TypeList::STRING
        );

        yield new FieldDefinition(
            'IBLOCK_SECTION_ID',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'LEFT_MARGIN',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'RIGHT_MARGIN',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'DEPTH_LEVEL',
            TypeList::NULL
        );

        yield new FieldDefinition(
            'TIMESTAMP_X',
            TypeList::NULL
        );
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getIterator(): Iterator
    {
        foreach ($this->getSectionsFields() as $field) {
            yield $field;
        }

        if (empty($this->ufReader)) {
            $this->ufReader = new UserFieldReader("IBLOCK_" . $this->getIblockId() . "_SECTION");
        }

        foreach ($this->ufReader as $field) {
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
