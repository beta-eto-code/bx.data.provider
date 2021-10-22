<?php

namespace BX\Data\Provider\Commands\Helpers;

class FieldDefinition
{
    /**
     * @var bool
     */
    public $isRequired = false;
    /**
     * @var bool
     */
    public $isUnique = false;
    /**
     * @var mixed
     */
    public $defaultValue = null;
    /**
     * @var string
     */
    public $type = TypeList::STRING;

    /**
     * @var string
     */
    public $name = '';

    public function __construct(
        string $name,
        string $type,
               $defaultValue = null,
        bool   $isRequired = false,
        bool   $isUnique = false
    )
    {
        $this->name = $name;
        $this->type = $type;
        $this->defaultValue = $defaultValue;
        $this->isRequired = $isRequired;
        $this->isUnique = $isUnique;
    }

    /**
     * @param string $name
     * @param array $values
     * @param bool $isRequired
     * @return FieldDefinition
     */
    public static function initEnum(string $name, array $values, bool $isRequired = false): FieldDefinition
    {
        return new static($name, TypeList::ENUM, $values, $isRequired);
    }
}