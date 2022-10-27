<?php

namespace BX\Data\Provider\Commands;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Config\Option;
use Data\Provider\Interfaces\MigrateResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Data\Provider\Interfaces\StatisticMigrateResultInterface;
use Data\Provider\StatisticMigrateResult;
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
     * @param OutputInterface $output
     * @param string $className
     * @param MigrateResultInterface|StatisticMigrateResultInterface $result
     * @param string $type
     * @param bool $isVerbose
     * @return void
     * @throws Exception
     */
    private function printResult(
        OutputInterface $output,
        string $className,
        $result,
        string $type,
        bool $isVerbose = false
    ): void {
        if ($result instanceof MigrateResultInterface) {
            $this->printMigrateResult($output, $className, $result, $type, $isVerbose);
            return;
        }

        if ($result instanceof StatisticMigrateResultInterface) {
            $this->printStatisticResult($output, $className, $result, $type, $isVerbose);
        }
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
    private function printMigrateResult(
        OutputInterface $output,
        string $className,
        MigrateResultInterface $result,
        string $type,
        bool $isVerbose = false
    ) {
        $statisticResult = StatisticMigrateResult::initFromMigrateResult($result);
        $this->printStatisticResult($output, $className, $statisticResult, $type, $isVerbose);
    }

    /**
     * @param OutputInterface $output
     * @param string $className
     * @param StatisticMigrateResultInterface $result
     * @param string $type
     * @param bool $isVerbose
     * @return void
     * @throws Exception
     */
    private function printStatisticResult(
        OutputInterface $output,
        string $className,
        StatisticMigrateResultInterface $result,
        string $type,
        bool $isVerbose = false
    ): void {
        $message = $result->getResultMessage() ?:
            $this->buildMessageFromStatisticResultAndType($result, $className, $type);

        $this->printText($output, $message);
        $migrateResult = $result->getMigrateResult();
        if (!$isVerbose || empty($migrateResult)) {
            return;
        }

        foreach ($migrateResult->getResultList() as $r) {
            foreach ($r->getIterator() as $itemResult) {
                /**
                 * @var PkOperationResultInterface $itemResult
                 */
                if ($itemResult->hasError()) {
                    $this->printText($output, "\nError: " . $itemResult->getErrorMessage(), 'error');
                } else {
                    $this->printText($output, "\nSuccess: " . json_encode($itemResult->getData()), 'info');
                }
            }
        }
    }

    private function buildMessageFromStatisticResultAndType(
        StatisticMigrateResultInterface $result,
        string $className,
        string $type
    ): string {
        $baseText = '- ' . $result->getSuccessCount() . ', ошибок - ' . $result->getErrorCount();
        switch ($type) {
            case 'import':
                return "$className: импортировано $baseText";
            case 'export':
                return "$className: экспортировано $baseText";
            case 'generate':
                return "$className: сгенерировано $baseText";
        }


        return "$className: обработано $baseText";
    }

    private function printText(OutputInterface $output, string $text, string $tag = 'fire'): void
    {
        $outputStyle = new OutputFormatterStyle('black', '#ff0', ['bold']);
        $output->getFormatter()->setStyle('fire', $outputStyle);
        $output->writeln("\n\n<$tag>$text</$tag>");
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
     * @psalm-suppress UnresolvableInclude,PossiblyUndefinedArrayOffset
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $runCount = 0;
        $isVerbose = (bool) $input->getOption('verbose');
        $runCounterData = $this->getRunCounterData();
        $commandsForRun = $this->getCommandsForRun($input);
        foreach ($commandsForRun as $command) {
            $isSuccess = $this->runCommand($output, $command, $runCounterData, $isVerbose);
            if ($isSuccess) {
                $runCount++;
            }
        }

        if ($runCount === 0) {
            $this->printText($output, 'Команды для запуска не найдены', 'error');
        }

        $this->saveRunCounterData($runCounterData);

        return 0;
    }

    /**
     * @param InputInterface $input
     * @return array
     * @psalm-suppress PossiblyUndefinedArrayOffset
     */
    private function getCommandsForRun(InputInterface $input): array
    {
        $type = $input->getOption('type');
        $className = $input->getOption('className');
        $isNew = $input->getOption('new');
        $basePath = $_SERVER['DOCUMENT_ROOT'] . "/local/dp/tasks/";

        $runCounterData = $this->getRunCounterData();
        $commandForSort = [];
        $commandForRun = [];
        foreach ($this->loadAndGetClassListByPath("$basePath$type") as $file => $class) {
            try {
                if (!$this->isValidClass($class)) {
                    continue;
                }

                $currentType = $this->getTypeCommandFromFilePath($basePath, $file);
                $currentShortName = $this->getClassBaseName($class);
                if ($isNew && $this->getRunCountByClassName($currentShortName, $runCounterData) > 0) {
                    continue;
                }

                $sortIndex = $this->getSortIndexFromClass($class);
                $commandData = [
                    'class' => $class,
                    'shortName' => $currentShortName,
                    'type' => $currentType,
                ];

                if (!empty($className)) {
                    if ($currentShortName === $className) {
                        if ($sortIndex !== null) {
                            $commandForSort[$sortIndex][] = $commandData;
                        } else {
                            $commandForRun[] = $commandData;
                        }

                        break;
                    }

                    continue;
                }

                /**
                 * @var MigrateResultInterface $result
                 */
                $this->incrementRunCounter($currentShortName, $runCounterData);
                if ($sortIndex !== null) {
                    $commandForSort[$sortIndex][] = $commandData;
                } else {
                    $commandForRun[] = $commandData;
                }
            } catch (\Throwable $e) {
            }
        }

        if (!empty($commandForSort)) {
            $commandForRun = array_merge($this->getSortedCommandList($commandForSort), $commandForRun);
        }

        return $commandForRun;
    }

    /**
     * @param string $path
     * @return array<string,string>
     */
    private function loadAndGetClassListByPath(string $path): array
    {
        $classList = [];
        foreach (glob("$path/*.php") as $file) {
            $class = $this->getClassFromFile($file);
            $classList[$file] = $class;
        }

        return $classList;
    }

    /**
     * @param OutputInterface $output
     * @param array $command
     * @param array $runCounterData
     * @param bool $isVerbose
     * @return bool
     */
    private function runCommand(OutputInterface $output, array $command, array &$runCounterData, bool $isVerbose): bool
    {
        try {
            $class = $command['class'] ?? null;
            if (empty($class) || !$this->isValidClass($class)) {
                return false;
            }

            $shortName = $command['shortName'] ?? '';
            if (!empty($shortName)) {
                $this->incrementRunCounter($shortName, $runCounterData);
            }
            /**
             * @psalm-suppress InvalidStringClass
             */
            $result = (new $class())->run();

            $type = $command['type'] ?? 'other';
            $this->printResult($output, $shortName, $result, $type, $isVerbose);
            return true;
        } catch (\Throwable $e) {
            $this->printText($output, $e->getMessage(), 'error');
            return false;
        }
    }

    private function getClassFromFile(string $filePath): string
    {
        $classes = get_declared_classes();
        /**
         * @psalm-suppress UnresolvableInclude
         */
        require_once $filePath;
        $diff = array_diff(get_declared_classes(), $classes);
        return reset($diff);
    }

    private function isValidClass(string $class): bool
    {
        return is_a($class, DataProviderTaskInterface::class, true);
    }

    private function getTypeCommandFromFilePath(string $basePath, string $file): string
    {
        return current(explode('/', str_replace($basePath, '', $file)));
    }

    private function getClassBaseName(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }

    private function getSortIndexFromClass(string $class): ?int
    {
        /**
         * @psalm-suppress UndefinedClass
         */
        if (is_a($class, SortableInterface::class, true)) {
            return $class::getSort();
        }

        return null;
    }

    private function getSortedCommandList(array $unsortedCommandList): array
    {
        $sortedCommandList = [];
        ksort($unsortedCommandList);
        foreach ($unsortedCommandList as $sortIndexGroup) {
            foreach ($sortIndexGroup as $command) {
                $sortedCommandList[] = $command;
            }
        }

        return $sortedCommandList;
    }
}
