<?php

namespace BX\Data\Provider\UserField;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Error;
use Bitrix\Main\EventManager;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Data\DeleteResult;
use Bitrix\Main\ORM\Data\UpdateResult;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields\DateField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\ScalarField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserFieldTable;
use Bitrix\Tasks\Util\Entity\DateTimeField;
use CUserTypeManager;
use Exception;

class UfDataManagerFactory
{
    private static ?array $fieldTypesMap = null;

    /**
     * @throws SqlQueryException
     * @throws SystemException
     */
    public static function createUfDataManager(string $entityId): DataManager
    {
        $ufDataManager = new class extends DataManager {
            public static string $entityId = '';
            public static string $tableName = '';
            public static array $fieldMap = [];

            public static function getTableName(): string
            {
                return static::$tableName;
            }

            public static function getMap(): array
            {
                return static::$fieldMap;
            }

            public static function add(array $data): AddResult
            {
                $addResult = new AddResult();
                $valueId = $data['ID'] ?? ($data['VALUE_ID'] ?? null);
                if (empty($valueId)) {
                    return $addResult->addError(new Error('VALUE_ID is empty'));
                }

                unset($data['VALUE_ID'], $data['ID']);
                $addResult->setId($valueId);
                $updateResult = static::update($valueId, $data);
                if (!$updateResult->isSuccess()) {
                    $addResult->addErrors($updateResult->getErrors());
                }
                return $addResult;
            }

            public static function update($primary, array $data): UpdateResult
            {
                /**
                 * @var CUserTypeManager $USER_FIELD_MANAGER
                 */
                global $USER_FIELD_MANAGER;
                $isSuccess = $USER_FIELD_MANAGER->Update(static::$entityId, $primary, $data);
                $updateResult = new UpdateResult();
                if (!$isSuccess) {
                    $updateResult->addError(new Error('Undefined error on save UF in ' . static::$entityId));
                }
                return $updateResult;
            }

            public static function delete($primary): DeleteResult
            {
                /**
                 * @var CUserTypeManager $USER_FIELD_MANAGER
                 */
                global $USER_FIELD_MANAGER;
                $USER_FIELD_MANAGER->Delete(static::$entityId, $primary);
                return new DeleteResult();
            }

            public static function getList(array $parameters = array())
            {
                $filter = $parameters['filter'];
                foreach ($filter as $name => $value) {
                    $newName = static::getNewNameForFilter($name);
                    if ($name !== $newName) {
                        unset($parameters['filter'][$name]);
                        $parameters['filter'][$newName] = $value;
                    }
                }

                if (!empty($filter['ID'] ?? '')) {
                    $parameters['filter']['VALUE_ID'] = $filter['ID'];
                    unset($parameters['filter']['ID']);
                }
                return parent::getList($parameters);
            }

            private static function getNewNameForFilter(string $name): string
            {
                [$prefix, $fieldName] = static::parseFilterName($name);
                $field = static::findMultiUserFieldByFieldName($fieldName);
                return $field instanceof MultiUserField ? $prefix . $field->getFieldNameForFilter() : $name;
            }

            private static function findMultiUserFieldByFieldName(string $fieldName): ?MultiUserField
            {
                $filed = static::$fieldMap[$fieldName] ?? null;
                return $filed instanceof MultiUserField ? $filed : null;
            }

            private static function parseFilterName(string $name): array
            {
                switch (true) {
                    case strpos($name, '<=') === 0:
                        $name = str_replace('<=', '', $name);
                        return ['<=', $name];
                    case strpos($name, '>=') === 0:
                        $name = str_replace('>=', '', $name);
                        return ['>=', $name];
                    case strpos($name, '!%') === 0:
                        $name = str_replace('!%', '', $name);
                        return ['!%', $name];
                    case strpos($name, '><') === 0:
                        $name = str_replace('><', '', $name);
                        return ['><', $name];
                    case strpos($name, '%') === 0:
                        $name = str_replace('%', '', $name);
                        return ['%', $name];
                    case strpos($name, '!') === 0:
                        $name = str_replace('!', '', $name);
                        return ['!', $name];
                    case strpos($name, '<') === 0:
                        $name = str_replace('<', '', $name);
                        return ['<', $name];
                    case strpos($name, '>') === 0:
                        $name = str_replace('>', '', $name);
                        return ['>', $name];
                    default:
                        $name = str_replace('=', '', $name);
                        return ['=', $name];
                }
            }

            public static function getEntity()
            {
                if (!isset(static::$entity[static::$entityId])) {
                    throw new Exception('OMG! Entity is not init!');
                }

                return static::$entity[static::$entityId];
            }

            public function initEntity(): void
            {
                $entity = new ORMEntity(static::$entityId);
                $entity->initialize($this);
                $entity->postInitialize();

                // call user-defined postInitialize
                $this::postInitialize($entity);
                static::$entity[static::$entityId] = $entity;
            }
        };

        $ufDataManager::$entityId = $entityId;
        $tableName = 'b_uts_' . mb_strtolower($entityId);
        $ufDataManager::$tableName = $tableName;

        $fieldMap = [];
        $fieldMap['VALUE_ID'] = new IntegerField('VALUE_ID', ['primary' => true, 'required' => true]);
        $userFieldsConfig = static::getUFConfig($entityId);

        $utmEntity = static::createUTMUFDataManager($entityId)::getEntity();
        foreach ($userFieldsConfig as $fieldConfig) {
            $field = static::createFieldByConfig($fieldConfig, $utmEntity);
            $fieldMap[$field->getName()] = $field;

            $fieldType = $fieldConfig['USER_TYPE_ID'] ?? '';
            if ($field instanceof MultiUserField) {
                $utmFieldName = $field->getUrmFieldName();
                $fieldMap[$utmFieldName] = $field->getOrCreateReferenceField();
            } elseif ($fieldType === 'enumeration') {
                $enumFieldName = 'ENUM_' . $field->getName();
                $fieldMap[$enumFieldName] = new ReferenceField(
                    $enumFieldName,
                    UserFieldEnumTable::class,
                    Join::on('this.' . $field->getName(), 'ref.ID')
                );
            }
        }

        $ufDataManager::$fieldMap = $fieldMap;
        $ufDataManager->initEntity();
        return $ufDataManager;
    }

    public static function createUTMUFDataManager(string $entityId): DataManager
    {
        $ufDataManager = new class extends DataManager {
            public static string $entityId = '';
            public static string $tableName = '';

            public static function getTableName(): string
            {
                return static::$tableName;
            }

            public static function getMap(): array
            {
                return [
                    'ID' => new IntegerField('ID', ['required' => true, 'primary' => true]),
                    'VALUE_ID' => new IntegerField('VALUE_ID', ['required' => true]),
                    'FIELD_ID' => new IntegerField('FIELD_ID', ['required' => true]),
                    'VALUE' => new StringField('VALUE', ['required' => false]),
                    'VALUE_INT' => new IntegerField('VALUE_INT', ['required' => false]),
                    'VALUE_DOUBLE' => new FloatField('VALUE_DOUBLE', ['required' => false]),
                    'VALUE_DATE' => new DateField('VALUE_DATE', ['required' => false]),
                ];
            }

            public static function add(array $data): AddResult
            {
                $addResult = new AddResult();
                $valueId = $data['VALUE_ID'] ?? null;
                if (empty($valueId)) {
                    return $addResult->addError(new Error('VALUE_ID is empty'));
                }

                unset($data['VALUE_ID']);
                $addResult->setId($valueId);
                $updateResult = static::update($valueId, $data);
                if (!$updateResult->isSuccess()) {
                    $addResult->addErrors($updateResult->getErrors());
                }
                return $addResult;
            }

            public static function update($primary, array $data): UpdateResult
            {
                /**
                 * @var CUserTypeManager $USER_FIELD_MANAGER
                 */
                global $USER_FIELD_MANAGER;
                $isSuccess = $USER_FIELD_MANAGER->Update(static::$entityId, $primary, $data);
                $updateResult = new UpdateResult();
                if (!$isSuccess) {
                    $updateResult->addError(new Error('Undefined error on save UF in ' . static::$entityId));
                }
                return $updateResult;
            }

            public static function delete($primary): DeleteResult
            {
                /**
                 * @var CUserTypeManager $USER_FIELD_MANAGER
                 */
                global $USER_FIELD_MANAGER;
                $USER_FIELD_MANAGER->Delete(static::$entityId, $primary);
                return new DeleteResult();
            }

            public static function getEntity()
            {
                if (!isset(static::$entity[static::$entityId])) {
                    throw new Exception('OMG! Entity is not init!');
                }

                return static::$entity[static::$entityId];
            }

            public function initEntity(): void
            {
                $entity = new ORMEntity(static::$entityId);
                $entity->initialize($this);
                $entity->postInitialize();

                // call user-defined postInitialize
                $this::postInitialize($entity);
                static::$entity[static::$entityId] = $entity;
            }
        };

        $ufDataManager::$entityId = $entityId;
        $tableName = 'b_utm_' . mb_strtolower($entityId);
        $ufDataManager::$tableName = $tableName;
        $ufDataManager->initEntity();
        return $ufDataManager;
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    private static function getUFConfig(string $entityId): array
    {
        return UserFieldTable::getList([
            'select' => [
                'ID',
                'FIELD_NAME',
                'USER_TYPE_ID',
                'MULTIPLE',
                'MANDATORY',
            ],
            'filter' => [
                '=ENTITY_ID' => $entityId
            ],
            'cache' => [
                'ttl' => 3600
            ]
        ])->fetchAll();
    }

    /**
     * @throws SystemException
     */
    private static function createFieldByConfig(array $fieldConfig, Entity $utmEntity): ScalarField
    {
        $fieldId = (int)($fieldConfig['ID'] ?? 0);
        $fieldName = $fieldConfig['FIELD_NAME'] ?? '';
        $fieldType = $fieldConfig['USER_TYPE_ID'] ?? '';
        $isRequired = ($fieldConfig['MANDATORY'] ?? 'N') === 'Y';
        $isMultiple = ($fieldConfig['MULTIPLE'] ?? 'N') === 'Y';
        return static::createField(
            $fieldName,
            $fieldType,
            $fieldId,
            $isRequired,
            $isMultiple,
            $utmEntity
        );
    }

    /**
     * @throws SystemException
     */
    private static function createField(
        string $fieldName,
        string $fieldType,
        int $fieldId,
        bool $isRequired,
        bool $isMultiple,
        Entity $utmEntity
    ): ScalarField {
        if ($isMultiple) {
            return new MultiUserField(
                $fieldName,
                $fieldType,
                $fieldId,
                $utmEntity,
                ['required' => $isRequired]
            );
        }

        $fieldTypesMap = static::getFieldTypesMap();
        switch ($fieldTypesMap[$fieldType] ?? '') {
            case CUserTypeManager::BASE_TYPE_INT:
            case CUserTypeManager::BASE_TYPE_FILE:
                return new IntegerField($fieldName, ['required' => $isRequired]);
            case CUserTypeManager::BASE_TYPE_DOUBLE:
                return new FloatField($fieldName, ['required' => $isRequired]);
            case CUserTypeManager::BASE_TYPE_DATETIME:
                return new DateTimeField($fieldName, ['required' => $isRequired]);
            default:
                return new StringField($fieldName, ['required' => $isRequired]);
        }
    }

    public static function getFieldTypesMap(): array
    {
        if (!is_null(static::$fieldTypesMap)) {
            return static::$fieldTypesMap;
        }

        $eventManager = EventManager::getInstance();
        foreach ($eventManager->findEventHandlers("main", "OnUserTypeBuildList") as $event) {
            $eventResult = ExecuteModuleEventEx($event);
            $userTypeId = $eventResult['USER_TYPE_ID'] ?? '';
            $baseType = $eventResult['BASE_TYPE'] ?? '';
            static::$fieldTypesMap[$userTypeId] = $baseType;
        }

        return static::$fieldTypesMap;
    }
}
