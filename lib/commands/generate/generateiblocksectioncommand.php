<?php

namespace BX\Data\Provider\Commands\Generate;

use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use BX\Data\Provider\Commands\DataProviderTaskInterface;
use BX\Data\Provider\Commands\Helpers\IblockSectionReader;
use BX\Data\Provider\Commands\Helpers\TaskBuilder;
use BX\Data\Provider\SectionIblockDataProvider;
use Data\Provider\DefaultDataMigrator;
use Data\Provider\Interfaces\MigrateResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Providers\ClosureDataProvider;
use Data\Provider\QueryCriteria;
use Faker\Factory;
use Nette\PhpGenerator\PhpFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateIblockSectionCommand extends Command
{

    protected function configure()
    {
        $this->setName('dp:sectiongen')
            ->setDescription('Генерация разделов инфоблока')
            ->addArgument('type', InputArgument::REQUIRED, 'Тип инфоблока')
            ->addArgument('code', InputArgument::REQUIRED, 'Код инфоблока')
            ->addOption(
                'className',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Имя генерируемого класса'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Количество генерируемых записей',
                10
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws SystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        $code = $input->getArgument('code');
        $className = $input->getOption('className') ?? 'SectionGen' . ucfirst($type) . ucfirst($code);
        $className = TaskBuilder::toCamelCaseString($className);
        $limit = (int)$input->getOption('limit');

        $phpFile = new PhpFile();
        $namespace = $phpFile->addNamespace("Bx\\Data\\Provider\\Tasks\\Generate");
        $namespace->addUse(SectionIblockDataProvider::class);
        $namespace->addUse(Factory::class);
        $namespace->addUse(ClosureDataProvider::class);
        $namespace->addUse(QueryCriteriaInterface::class);
        $namespace->addUse(QueryCriteria::class);
        $namespace->addUse(DefaultDataMigrator::class);
        $namespace->addUse(DateTime::class);
        $namespace->addUse(Date::class);
        $namespace->addUse(MigrateResultInterface::class);
        $namespace->addUse(DataProviderTaskInterface::class);

        $class = $namespace->addClass($className);
        $class->addImplement(DataProviderTaskInterface::class);
        $runMethod = $class->addMethod("run");
        $runMethod->setReturnType(MigrateResultInterface::class);

        $dataMapString = TaskBuilder::getFakerDataMapString(
            new IblockSectionReader($type, $code),
            '$fakerFactory'
        );

        $runMethod->setBody(<<<PHP
\$fakerFactory = Factory::create('ru_RU');
\$sourceProvider = new ClosureDataProvider($limit, function (QueryCriteriaInterface \$query) use (\$fakerFactory) {
    return $dataMapString;
});

\$targetProvider = new SectionIblockDataProvider("$type", "$code");
\$query = new QueryCriteria();
\$migrator = new DefaultDataMigrator(
    \$sourceProvider,
    \$targetProvider
);
return \$migrator->runInsert(\$query);
PHP
        );

        TaskBuilder::saveFile(
            $_SERVER['DOCUMENT_ROOT'].'/local/dp/tasks/generate/'.strtolower($className).'.php',
            $phpFile
        );

        return 0;
    }
}