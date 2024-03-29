<?php

namespace BX\Data\Provider\Commands\Import;

use BX\Data\Provider\Commands\DataProviderTaskInterface;
use BX\Data\Provider\Commands\Helpers\TaskBuilder;
use BX\Data\Provider\UserDataProvider;
use Data\Provider\DefaultDataMigrator;
use Data\Provider\Interfaces\MigrateResultInterface;
use Data\Provider\Providers\CsvDataProvider;
use Data\Provider\Providers\JsonDataProvider;
use Data\Provider\Providers\XmlDataProvider;
use Data\Provider\QueryCriteria;
use Exception;
use Nette\PhpGenerator\PhpFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportUserCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('dp:userimport')
            ->setDescription('Импорт пользователей')
            ->addArgument('file', InputArgument::REQUIRED, 'Путь к файлу для импорта')
            ->addOption(
                'className',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Имя генерируемого класса'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     * @psalm-suppress PossiblyUndefinedArrayOffset
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = $input->getArgument('file');
        $pathData = pathinfo($filePath);
        $ext = strtolower($pathData['extension'] ?? '');
        if (!in_array($ext, ['json', 'xml', 'csv'])) {
            throw new Exception('Invalid extension');
        }

        $className = $input->getOption('className') ?? 'UserImport' . ucfirst($ext);
        $className = TaskBuilder::toCamelCaseString($className);

        $phpFile = new PhpFile();
        $namespace = $phpFile->addNamespace("Bx\\Data\\Provider\\Tasks\\Import");
        $namespace->addUse(UserDataProvider::class);
        $namespace->addUse(QueryCriteria::class);
        $namespace->addUse(DefaultDataMigrator::class);
        $namespace->addUse(MigrateResultInterface::class);
        $namespace->addUse(DataProviderTaskInterface::class);

        $sourceProviderStr = '';
        switch ($ext) {
            case 'json':
                $namespace->addUse(JsonDataProvider::class);
                $sourceProviderStr = "new JsonDataProvider('$filePath', 'ID')";
                break;
            case 'xml':
                $namespace->addUse(XmlDataProvider::class);
                $sourceProviderStr = "new XmlDataProvider('$filePath', 'list', 'item', 'ID')";
                break;
            case 'csv':
                $namespace->addUse(CsvDataProvider::class);
                $sourceProviderStr = "new CsvDataProvider('$filePath', 'ID', ';')";
                break;
        }

        $class = $namespace->addClass($className);
        $class->addImplement(DataProviderTaskInterface::class);
        $runMethod = $class->addMethod("run");
        $runMethod->setReturnType(MigrateResultInterface::class);

        $runMethod->setBody(<<<PHP
\$sourceProvider = $sourceProviderStr;
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
            $_SERVER['DOCUMENT_ROOT'] . '/local/dp/tasks/import/' . strtolower($className) . '.php',
            $phpFile
        );

        return 0;
    }
}
