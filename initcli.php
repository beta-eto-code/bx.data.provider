<?php
use Bitrix\Main\Loader;
use Bx\Cli\Interfaces\BitrixCliAppInterface;
use BX\Data\Provider\Commands\Export\ExportHlBlockCommand;
use BX\Data\Provider\Commands\Export\ExportIblockCommand;
use BX\Data\Provider\Commands\Export\ExportIblockSectionCommand;
use BX\Data\Provider\Commands\Export\ExportTableCommand;
use BX\Data\Provider\Commands\Export\ExportUserCommand;
use BX\Data\Provider\Commands\Generate\GenerateHlBlockCommand;
use BX\Data\Provider\Commands\Generate\GenerateIblockCommand;
use BX\Data\Provider\Commands\Generate\GenerateIblockSectionCommand;
use BX\Data\Provider\Commands\Generate\GenerateTableCommand;
use BX\Data\Provider\Commands\Generate\GenerateUserCommand;
use BX\Data\Provider\Commands\Import\ImportHlBlockCommand;
use BX\Data\Provider\Commands\Import\ImportIblockCommand;
use BX\Data\Provider\Commands\Import\ImportIblockSectionCommand;
use BX\Data\Provider\Commands\Import\ImportTableCommand;
use BX\Data\Provider\Commands\Import\ImportUserCommand;
use BX\Data\Provider\Commands\RunTaskCommand;

/**
 * @var BitrixCliAppInterface $cliApp
 */

if (empty($cliApp) || !($cliApp instanceof BitrixCliAppInterface)) {
    return;
}

Loader::includeModule('bx.data.provider');
$cliApp->addCommand(new ExportIblockCommand());
$cliApp->addCommand(new ExportHlBlockCommand());
$cliApp->addCommand(new ExportIblockSectionCommand());
$cliApp->addCommand(new ExportTableCommand());
$cliApp->addCommand(new ExportUserCommand());

$cliApp->addCommand(new ImportIblockCommand());
$cliApp->addCommand(new ImportHlBlockCommand());
$cliApp->addCommand(new ImportIblockSectionCommand());
$cliApp->addCommand(new ImportTableCommand());
$cliApp->addCommand(new ImportUserCommand());

$cliApp->addCommand(new GenerateIblockCommand());
$cliApp->addCommand(new GenerateHlBlockCommand());
$cliApp->addCommand(new GenerateIblockSectionCommand());
$cliApp->addCommand(new GenerateTableCommand());
$cliApp->addCommand(new GenerateUserCommand());

$cliApp->addCommand(new RunTaskCommand());