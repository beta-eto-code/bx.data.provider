<?php

namespace BX\Data\Provider\Commands\Helpers;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Exception;

class HlBlockReader extends TableReader
{
    private function __construct(string $tableName)
    {
        parent::__construct($tableName);
    }

    /**
     * @param string $hlName
     * @return HlBlockReader
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public static function initByName(string $hlName): HlBlockReader
    {
        Loader::includeModule('highloadblock');
        $hlBlockData = HighloadBlockTable::getRow([
           'filter' => [
               '=NAME' => $hlName,
           ],
        ]);
        if (empty($hlBlockData)) {
            throw new Exception('Hl block is not found');
        }

        return new static($hlBlockData['TABLE_NAME']);
    }

    /**
     * @param int $hlId
     * @return HlBlockReader
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public static function initById(int $hlId): HlBlockReader
    {
        Loader::includeModule('highloadblock');
        $hlBlockData = HighloadBlockTable::getRow([
            'filter' => [
                '=ID' => $hlId,
            ],
        ]);
        if (empty($hlBlockData)) {
            throw new Exception('Hl block is not found');
        }

        return new static((int)$hlBlockData['ID']);
    }
}