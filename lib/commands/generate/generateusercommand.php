<?php

namespace BX\Data\Provider\Commands\Generate;

use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use BX\Data\Provider\Commands\DataProviderTaskInterface;
use BX\Data\Provider\Commands\Helpers\TaskBuilder;
use BX\Data\Provider\Commands\Helpers\UserEntityReader;
use BX\Data\Provider\UserDataProvider;
use Data\Provider\DefaultDataMigrator;
use Data\Provider\Interfaces\MigrateResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Providers\ClosureDataProvider;
use Data\Provider\QueryCriteria;
use Faker\Factory;
use Nette\PhpGenerator\PhpFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateUserCommand extends Command
{
    protected function configure()
    {
        $this->setName('dp:usergen')
            ->setDescription('Импорт пользователей')
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $className = $input->getOption('className') ?? 'UserGen';
        $className = TaskBuilder::toCamelCaseString($className);
        $limit = (int)$input->getOption('limit');

        $phpFile = new PhpFile();
        $namespace = $phpFile->addNamespace("Bx\\Data\\Provider\\Tasks\\Generate");
        $namespace->addUse(UserDataProvider::class);
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

        $dataMapString = TaskBuilder::getFakerDataMapString(new UserEntityReader(), '$fakerFactory');

        $runMethod->setBody(<<<PHP
\$fakerFactory = Factory::create('ru_RU');
\$sourceProvider = new ClosureDataProvider($limit, function (QueryCriteriaInterface \$query) use (\$fakerFactory) {
    return $dataMapString;
});

\$targetProvider = new UserDataProvider();
\$query = new QueryCriteria();
\$migrator = new DefaultDataMigrator(
    \$sourceProvider,
    \$targetProvider
);
return \$migrator->runInsert(\$query);
PHP
        );

        TaskBuilder::saveFile(
            $_SERVER['DOCUMENT_ROOT'] . '/local/dp/tasks/generate/' . strtolower($className) . '.php',
            $phpFile
        );

        return 0;
    }
}
