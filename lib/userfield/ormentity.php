<?php

namespace BX\Data\Provider\UserField;

use Bitrix\Main\Application;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\SystemException;
use Bitrix\Main\Text\StringHelper;
use Bitrix\Main\UserFieldTable;

class ORMEntity extends Entity
{
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function getFullName(): string
    {
        if (is_object($this->className)) {
            return get_class($this->className);
        }

        return parent::getFullName();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function postInitialize()
    {
        // basic properties
        $classPath = explode('\\', ltrim($this->getClassName(), '\\'));
        $this->name = substr(end($classPath), 0, -5);

        // default db table name
        if (is_null($this->dbTableName))
        {
            $_classPath = array_slice($classPath, 0, -1);

            $this->dbTableName = 'b_';

            foreach ($_classPath as $i => $_pathElem)
            {
                if ($i == 0 && $_pathElem == 'Bitrix')
                {
                    // skip bitrix namespace
                    continue;
                }

                if ($i == 1 && $_pathElem == 'Main')
                {
                    // also skip Main module
                    continue;
                }

                $this->dbTableName .= strtolower($_pathElem).'_';
            }

            // add class
            if ($this->name !== end($_classPath))
            {
                $this->dbTableName .= StringHelper::camel2snake($this->name);
            }
            else
            {
                $this->dbTableName = substr($this->dbTableName, 0, -1);
            }
        }

        $this->primary = array();
        $this->references = array();

        // attributes
        foreach ($this->fieldsMap as $fieldName => &$fieldInfo)
        {
            $this->addField($fieldInfo, $fieldName);
        }

        if (!empty($this->fieldsMap) && empty($this->primary))
        {
            throw new SystemException(sprintf('Primary not found for %s Entity', $this->name));
        }

        // attach userfields
        if (empty($this->uf_id))
        {
            // try to find ENTITY_ID by map
            $userTypeManager = Application::getUserTypeManager();
            if($userTypeManager instanceof \CUserTypeManager)
            {
                $entityList = $userTypeManager->getEntityList();
                $ufId = is_array($entityList) ? array_search($this->className, $entityList) : false;
                if ($ufId !== false)
                {
                    $this->uf_id = $ufId;
                }
            }
        }

        if (!empty($this->uf_id))
        {
            // attach uf fields and create uts/utm entities
            UserFieldTable::attachFields($this, $this->uf_id);

            // save index
            static::$ufIdIndex[$this->uf_id] = $this->className;
        }
    }

    private function getClassName(): string
    {
        return is_object($this->className) ? get_class($this->className) : $this->className;
    }
}
