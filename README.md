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
        'content' => urlencode($data['DETAIL_TEXT']),
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