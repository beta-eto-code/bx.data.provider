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
        $this->MODULE_VERSION = "1.18.2";
        $this->MODULE_VERSION_DATE = "2024-04-03 18:00:00";
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
        $this->InstallFiles();
        ModuleManager::RegisterModule($this->MODULE_ID);

        return true;
    }

    public function DoUninstall()
    {
        $this->UnInstallFiles();
        ModuleManager::UnRegisterModule($this->MODULE_ID);

        return true;
    }

    public function InstallFiles()
    {
        if (!ModuleManager::isModuleInstalled('bx.cli')) {
            CopyDirFiles(__DIR__ . "/files", $_SERVER["DOCUMENT_ROOT"]);
        }

        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(__DIR__ . "/files", $_SERVER["DOCUMENT_ROOT"]);
        return true;
    }
}
