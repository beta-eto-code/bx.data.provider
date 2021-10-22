# Bitrix data provider

Расширение библиотеки beta/data.provider для Bitrix. Данный модуль включает в себя реализацию след. провайдеров:

* IblockDataProvider - провайдер для работы с данными инфоблоков
* SectionIblockDataProvider - провайдер для работы с данными разделов инфоблоков
* HlBlockDataProvider - провайдер для работы с данными hl блоков
* DataManagerDataProvider - провайдер для работы с произвольными менеджерами данных bitrix (наследники класса DataManager)
* BxConnectionDataProvider - провайдер для работы с данными через подключения bitrix, необходимо указать конструктор запросов и имя подключения
* UserDataProvider - провайдер для работы с пользователями bitrix

## Пример экспорта данных инфоблока в json файл

```php
use BX\Data\Provider\IblockDataProvider;
use Data\Provider\Providers\JsonDataProvider;
use Data\Provider\DefaultDataMigrator;
use Data\Provider\QueryCriteria;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\Interfaces\CompareRuleInterface;

$newsProvider = new IblockDataProvider(
    'content',                              // передаем тип инфоблока
    'news'                                  // передаем символьный код инфоблока
);

$newsProvider->setMapperForRead(function (array $data): array {     // задаем маппер данных при запросе данных от провайдера
    return [
        'id' => (int)$data['ID'],
        'name' => $data['NAME'],
        'code' => $data['CODE'],
        'content' => $data['DETAIL_TEXT'],
    ];
});

$targetJsonProvider = new JsonDataProvider(
    $_SERVER['DOCUMENT_ROOT'].'/upload/news.json',  // указываем путь к файлу для сохранения
    'id'                                            // указываем имя первичного ключа
);

$migrator = new DefaultDataMigrator(                // создаем объект для обмена данными
    $newsProvider,                                  // источник данных
    $targetJsonProvider                             // приемник данных
);

$queryNews = new QueryCriteria();
$queryNews->addCriteria('ACTIVE', CompareRuleInterface::EQUAL, 'Y');    // сохранять в json файл будем только активные элементы
$exportResult = $migrator->runUpdate(
    $queryNews,         // передаем фильтр для источника данных
    'ID'                // здесь указываем либо ключ для сопоставления данных с первичным ключом приемника данных или же анонимную функцию вида function(array $dataItem): QueryCriteriaInterface
);

$exportResult->hasErrors();             // есть ли ошибки
$exportResult->getErrors();             // список ошибок
$exportResult->getSourceData();         // данные для экспорта полученные от источника
$exportResult->getUnimportedDataList(); // данные которые не удалось сохранить в json файл
```

## BxQueryAdapter - адаптер параметров запроса Bitrix, пример использования

```php
use BX\Data\Provider\BxQueryAdapter;
use Data\Provider\QueryCriteria;
use Data\Provider\Providers\JsonDataProvider;
use Data\Provider\Interfaces\CompareRuleInterface;

$bxParams = [
    'select' => ['NAME', 'CODE', 'ID'],
    'filter' => [   
        '=ACTIVE' => 'Y',
        [
            'LOGIC' => 'OR',
            [
                '=NAME' => 'test',
            ],
            [
                '=NAME' => 'other',
            ],
        ],
    ],
    'limit' => 10,
];

$bxQuery = BxQueryAdapter::initFromArray($bxParams);
$jsonProvider = new JsonDataProvider(
    $_SERVER['DOCUMENT_ROOT'].'/users.json',    // указываем путь к json файлу
    'id'                                        // указываем первичный ключ
);

$jsonProvider->getData($bxQuery->getQuery());   // данные из json файла

$newQuery = new QueryCriteria();
$newQuery->setSelect(['NAME', 'CODE', 'ID']);
$newQuery->setLimit(10);
$compareRule = $newQuery->addCriteria('ACTIVE', CompareRuleInterface::EQUAL, 'Y');
$compareRule->and('NAME', CompareRuleInterface::EQUAL, 'test')
    ->or('NAME', CompareRuleInterface::EQUAL, 'other');

$newBxQuery = BxQueryAdapter::init($newQuery);
$newBxQuery->toArray();     // результат будет аналогичен $bxParams
```

## CLI

После установки модуля в корене проекта добавиться исполняемый файл dpcli (в том случае если в проекте не был ранее 
установлен модуль beta/bx.cli, в этом случае команды будут добавлены к общему исполняемому файлу - bxcli).

Данный интерфейс предоставляет команды для генерации файлов выполняющих задачи импорта, экспорта и генерации данных. 
Сами файлы представляют из себя шаблоны, то есть код генерируемый в файлах следует редактировать под свои нужды и требования.

Команды для создания задач экспорта:

* ./dpcli dp:hlexport [код hl блока] [путь к файлу для экспорта данных.json|csv|xml] - экспорт данных указанного hl блока, экспорт поддерживается в json, csv и xml форматах
* ./dpcli dp:iblockexport [тип инфоблока] [код инфоблока] [путь к файлу для экспорта данных.json|csv|xml] - экспорт элементов указанного инфоблока, экспорт поддерживается в json, csv и xml форматах
* ./dpcli dp:sectionexport [тип инфоблока] [код инфоблока] [путь к файлу для экспорта данных.json|csv|xml] - экспорт разделов указанного инфоблока, экспорт поддерживается в json, csv и xml форматах
* ./dpcli dp:tableexport [имя таблицы] [путь к файлу для экспорта данных.json|csv|xml] - экспорт записей из указанной таблицы, экспорт поддерживается в json, csv и xml форматах
* ./dpcli dp:userexport [путь к файлу для экспорта данных.json|csv|xml] - экспорт пользователей bitrix, экспорт поддерживается в json, csv и xml форматах

Команды для создания задач импорта:

* ./dpcli dp:hlimport [код hl блока] [путь к файлу для импорта данных.json|csv|xml] - импорт данных в указанный hl блок из указанного файла, импорт поддерживается из json, csv и xml форматов
* ./dpcli dp:iblockimport [тип инфоблока] [код инфоблока] [путь к файлу для импорта данных.json|csv|xml] - импорт элементов в указанный инфоблок из указанного файла, импорт поддерживается из json, csv и xml форматов
* ./dpcli dp:sectionimport [тип инфоблока] [код инфоблока] [путь к файлу для импорта данных.json|csv|xml] - импорт разделов в указанный инфоблок из указанного файла, импорт поддерживается из json, csv и xml форматов
* ./dpcli dp:tableimport [имя таблицы] [путь к файлу для импорта данных.json|csv|xml] - импорт записей в указанную таблицу из указанного файла, импорт поддерживается из json, csv и xml форматов
* ./dpcli dp:userimport [код hl блока] [путь к файлу для импорта данных.json|csv|xml] - импорт пользователей из указанного файла, импорт поддерживается из json, csv и xml форматов

Команды для создания задач генерации данных (в проекте должна быть установлена dev зависимость fzaninotto/faker):

* ./dpcli dp:hlgen [код hl блока] [количество генерируемых записей] - генерация данных для указанного hl блока
* ./dpcli dp:iblockgen [тип инфоблока] [код инфоблока] [количество генерируемых записей] - генерация элементов для указанного инфоблока
* ./dpcli dp:sectiongen [тип инфоблока] [код инфоблока] [количество генерируемых записей] - генерация разделов для указанного инфоблока
* ./dpcli dp:tablegen [имя таблицы] [количество генерируемых записей] - генерация записей для указанной таблицы
* ./dpcli dp:usergen [количество генерируемых записей] - генерация пользователей

Все файлы задач сохраняются в директории local/dp/tasks. Для запуска задач можно использовать команду вида:

```bash
./dpcli dp:run --type=export -c UserGen -v --new
```

Где:

* --type=export - не обязательный параметр, указывает на тип выполняемых задач, может принимать значения: export, import и generate, если параметр не указан будут исполняться все типы задач
* -c UserGen - не обязательный параметр, имя класса выполняемой задачи, данный параметр позволяет выполнить определенную задачу
* -v или --verbose - не обязательный параметр, подробный вывод результата задачи
* --new - не обязательный параметр, если он указан выполянться только новые задачи (которые ранее не выполнялись в текущем окружении)
