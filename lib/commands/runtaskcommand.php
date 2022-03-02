<?php

namespace BX\Data\Provider\Commands;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Config\Option;
use Data\Provider\Interfaces\MigrateResultInterface;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunTaskCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('dp:run')
            ->setDescription('Запуск задач')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Тип задач для выполнения, возможные значения: generate, export, import',
                '*'
            )
            ->addOption(
                'className',
                'c',
                InputOption::VALUE_REQUIRED,
                'Класс для выполнения'
            )
            ->addOption(
                'new',
                null, //'n',
                InputOption::VALUE_NONE,
                'Выполнить только новые задачи'
            );
    }

    /**
     * @param MigrateResultInterface $result
     * @return array
     */
    private function calcResult(MigrateResultInterface $result): array
    {
        $resultList = $result->getResultList();
        $count = 0;
        $errorCount = 0;
        foreach ($resultList as $r) {
            $count += $r->getResultCount();
            $errorCount += $r->getErrorResultCount();
        }

        $successCount = $count - $errorCount;

        return [$successCount, $errorCount];
    }

    /**
     * @param OutputInterface $output
     * @param string $className
     * @param MigrateResultInterface $result
     * @param string $type
     * @param bool $isVerbose
     * @return void
     * @throws Exception
     */
    private function printResult(
        OutputInterface $output,
        string $className,
        MigrateResultInterface $result,
        string $type,
        bool $isVerbose = false
    ) {
        [
            $successCount,
            $errorCount
        ] = $this->calcResult($result);

        $outputStyle = new OutputFormatterStyle('red', '#ff0', ['bold']);
        $output->getFormatter()->setStyle('fire', $outputStyle);

        switch ($type) {
            case 'import':
                $output->writeln(
                    "\n\n<fire>$className: импортировано - $successCount, ошибок импорта - $errorCount</fire>"
                );
                break;
            case 'export':
                $output->writeln(
                    "\n\n<fire>$className: экспортировано - $successCount, ошибок экспорта - $errorCount</fire>"
                );
                break;
            default:
                $output->writeln(
                    "\n\n<fire>$className: сгенерировано - $successCount, ошибок генерации - $errorCount</fire>"
                );
        }

        if (!$isVerbose) {
            return;
        }

        foreach ($result->getResultList() as $r) {
            foreach ($r->getIterator() as $itemResult) {
                /**
                 * @var PkOperationResultInterface $itemResult
                 */
                if ($itemResult->hasError()) {
                    $output->writeln("\n<error>Error: " . $itemResult->getErrorMessage() . '</error>');
                } else {
                    $output->writeln("\n<info>Success: " . json_encode($itemResult->getData()) . '</info>');
                }
            }
        }
    }

    /**
     * @param string $className
     * @return void
     * @param array $dataResult
     */
    private function incrementRunCounter(string $className, array &$dataResult)
    {
        $dataResult[$className] = $this->getRunCountByClassName($className, $dataResult) + 1;
    }

    /**
     * @param array $dataResult
     * @return void
     * @throws ArgumentOutOfRangeException
     */
    private function saveRunCounterData(array $dataResult)
    {
        Option::set('bx.data.provider', 'run_counter', json_encode($dataResult));
    }

    /**
     * @return array
     * @throws ArgumentOutOfRangeException
     * @throws ArgumentNullException
     */
    private function getRunCounterData(): array
    {
        $optionData = Option::get('bx.data.provider', 'run_counter', null);
        return empty($optionData) ? [] : (json_decode($optionData, true) ?? []);
    }

    /**
     * @param string $className
     * @param array $runCounterData
     * @return int
     */
    private function getRunCountByClassName(string $className, array $runCounterData): int
    {
        return (int)($runCounterData[$className] ?? 0);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     * @psalm-suppress UnresolvableInclude
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getOption('type');
        $className = $input->getOption('className');
        $isVerbose = $input->getOption('verbose');
        $isNew = $input->getOption('new');
        $basePath = $_SERVER['DOCUMENT_ROOT'] . "/local/dp/tasks/";

        $runCounterData = $this->getRunCounterData();
        foreach (glob("$basePath$type/*.php") as $file) {
            try {
                $classes = get_declared_classes();
                require_once $file;
                $diff = array_diff(get_declared_classes(), $classes);
                $class = reset($diff);
                if (!is_a($class, DataProviderTaskInterface::class, true)) {
                    continue;
                }

                $currentType = current(explode('/', str_replace($basePath, '', $file)));
                $currentShortName = basename(str_replace('\\', '/', $class));
                if ($isNew && $this->getRunCountByClassName($currentShortName, $runCounterData) > 0) {
                    continue;
                }

                if (!empty($className)) {
                    if ($currentShortName === $className) {
                        $this->incrementRunCounter($currentShortName, $runCounterData);
                        $result = (new $class())->run();
                        $this->printResult($output, $currentShortName, $result, $currentType, $isVerbose);
                        break;
                    }

                    continue;
                }

                /**
                 * @var MigrateResultInterface $result
                 */
                $this->incrementRunCounter($currentShortName, $runCounterData);
                $result = (new $class())->run();
                $this->printResult($output, $currentShortName, $result, $currentType, $isVerbose);
            } catch (\Throwable $e) {
                $currentShortName = $currentShortName ?? 'Unknown';
                $output->writeln("\n\n<error>$currentShortName: " . $e->getMessage() . "</error>");
            }
        }

        $this->saveRunCounterData($runCounterData);

        return 0;
    }
}
