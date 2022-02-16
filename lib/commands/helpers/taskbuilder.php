<?php

namespace BX\Data\Provider\Commands\Helpers;

use Bitrix\Main\SystemException;

class TaskBuilder
{
    /**
     * @param string $data
     * @return string
     */
    public static function toCamelCaseString(string $data): string
    {
        return implode(
            '',
            array_map(
                function ($value) {
                    return ucfirst($value);
                },
                explode(
                    '_',
                    str_replace('__', '_', $data)
                )
            )
        );
    }

    /**
     * @param string $file
     * @param $data
     * @throws SystemException
     */
    public static function saveFile(string $file, $data)
    {
        $fileInfo = pathinfo($file);
        $pathDir = $fileInfo['dirname'] ?? null;
        if (!empty($pathDir) && !is_dir($pathDir)) {
            $isSuccess = mkdir($pathDir, 0777, true);
            if (!$isSuccess) {
                throw new SystemException("Не удалось создать директорию: {$pathDir}");
            }
        }

        file_put_contents($file, $data);
    }

    /**
     * @param $value
     * @return mixed|string
     */
    private static function prepareValue($value)
    {
        if (is_null($value)) {
            return 'null';
        }

        if ($value === false) {
            return 'false';
        }

        if ($value === true) {
            return 'true';
        }

        if (is_string($value)) {
            return "'$value'";
        }

        if (is_object($value)) {
            if (method_exists($value, '_toString')) {
                $value = (string)$value;
                return "'$value'";
            }

            return 'null';
        }

        if (is_array($value)) {
            $value = array_map(function ($value) {
                return static::prepareValue($value);
            }, $value);
            return '[' . implode(', ', $value) . ']';
        }

        return $value;
    }

    /**
     * @param ReaderEntityInterface $readerEntity
     * @param string $fakerVarName
     * @return string
     */
    public static function getFakerDataMapString(ReaderEntityInterface $readerEntity, string $fakerVarName): string
    {
        $result = '';
        foreach ($readerEntity as $field) {
            if ($field->type === TypeList::AUTOINCREMEТ) {
                continue;
            }

            $strValue = static::getFackerMethodCallString($fakerVarName, $field->type, $field->defaultValue);
            if (empty($strValue)) {
                continue;
            }

            $result .= "\n\t\t\t'" . $field->name . "' => " . $strValue . ',';
        }

        return "[{$result}\n\t\t]";
    }

    /**
     * @param string $fakerVarName
     * @param string $valueType
     * @param null $defaultValue
     * @return string
     */
    public static function getFackerMethodCallString(string $fakerVarName, string $valueType, $defaultValue = null)
    {
        switch ($valueType) {
            case TypeList::UUID:
                return "{$fakerVarName}->uuid";
            case TypeList::NAME:
                return "{$fakerVarName}->firstName";
            case TypeList::ENUM:
                $enumList = static::prepareValue((array)$defaultValue);
                return "{$fakerVarName}->randomElement($enumList)";
            case TypeList::LAST_NAME:
                return "{$fakerVarName}->lastName";
            case TypeList::SECOND_NAME:
                return static::prepareValue($defaultValue);
            case TypeList::PHONE:
                return "{$fakerVarName}->phoneNumber";
            case TypeList::EMAIL:
                return "{$fakerVarName}->safeEmail";
            case TypeList::ADDRESS:
                return "{$fakerVarName}->address";
            case TypeList::CITY:
                return "{$fakerVarName}->city";
            case TypeList::COUNTRY:
                return "{$fakerVarName}->country";
            case TypeList::PASSWORD:
                return "{$fakerVarName}->password";
            case TypeList::LIST:
                return '[]';
            case TypeList::NULL:
            case TypeList::SECTION:
            case TypeList::ELEMENT:
                return static::prepareValue($defaultValue);
            case TypeList::FILE:
            case TypeList::NUMBER:
                return "{$fakerVarName}->randomNumber()";
            case TypeList::DATE_TIME:
                return "new DateTime({$fakerVarName}->dateTime->format('Y-m-d H:i:s'), 'Y-m-d H:i:s')";
            case TypeList::DATE_TIME_STRING:
                return "{$fakerVarName}->dateTime->format('Y-m-d H:i:s')";
            case TypeList::BOOLEAN:
                return "{$fakerVarName}->randomElement(['Y', 'N'])";
            case TypeList::DATE:
                return "new Date({$fakerVarName}->dateTime->format('Y-m-d'), 'Y-m-d')";
            case TypeList::DATE_STRING:
                return "{$fakerVarName}->dateTime->format('Y-m-d')";
            default:
                return "{$fakerVarName}->text";
        }
    }
}
