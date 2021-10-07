<?php

IncludeModuleLangFile(__FILE__);

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;

class bx_data_provider extends CModule
{
    public $MODULE_ID = "bx.data.provider";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $errors;

    public function __construct()
    {
        $this->MODULE_VERSION = "0.0.1";
        $this->MODULE_VERSION_DATE = "2021-10-07 14:24:04";
        $this->MODULE_NAME = "Провайдер данных";
        $this->MODULE_DESCRIPTION = "Провайдер данных";
    }

    /**
     * @param string $message
     */
    public function setError(string $message)
    {
        $GLOBALS["APPLICATION"]->ThrowException($message);
    }

    public function DoInstall(): bool
    {
        ModuleManager::RegisterModule($this->MODULE_ID);

        return true;
    }

    public function DoUninstall()
    {
        ModuleManager::UnRegisterModule($this->MODULE_ID);

        return true;
    }
}
