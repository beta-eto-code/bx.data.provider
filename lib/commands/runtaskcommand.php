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
            $this->buildMessageFromStatisticResultAndType($result, $type, $className);

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


        return "$className: сгенерировано $baseText";
    }

    private function printText(OutputInterface $output, string $text, string $tag = 'fire'): void
    {
        $outputStyle = new OutputFormatterStyle('red', '#ff0', ['bold']);
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
        $type = $input->getOption('type');
        $className = $input->getOption('className');
        $isVerbose = $input->getOption('verbose');
        $isNew = $input->getOption('new');
        $basePath = $_SERVER['DOCUMENT_ROOT'] . "/local/dp/tasks/";

        $runCount = 0;
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
                        $runCount++;
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
                $runCount++;
                $this->printResult($output, $currentShortName, $result, $currentType, $isVerbose);
            } catch (\Throwable $e) {
                $currentShortName = $currentShortName ?? 'Unknown';
                $this->printText($output, "\n\n$currentShortName: " . $e->getMessage(), 'error');
            }
        }

        if ($runCount === 0) {
            $this->printText($output, 'Команды для запуска не найдены', 'error');
        }

        $this->saveRunCounterData($runCounterData);

        return 0;
    }
}
