# Что это?

... расписать

# Возможности

...

# Установка

## Установка модуля

Если у вас в проекте уже используется composer:

```composer require SibirixScrum/sibirix-translator```

Если вы хотите использовать данный модуль переводов в проекте, где ещё нет композера: 
1. в папке /local/ выполнить ```composer require SibirixScrum/sibirix-translator```
1. подключить файл автозагрузки композера: в файле ```/local/php_interface/init.php``` (создайте, если он у вас отсутствует) добавить строку 
```require_once($_SERVER["DOCUMENT_ROOT"] . '/local/vendor/autoload.php');```

## Добавление обработчиков событий

Добавьте обработчики событий модуля:
в файле ```/local/php_interface/init.php```:

```
use Bitrix\Main\EventManager as BitrixEventManager;
use Sibirix\Translator\IBlockLocales;

$manager = BitrixEventManager::getInstance();
$manager->addEventHandler("main", "OnBeforeProlog", [IBlockLocales::class, 'onBeforeProlog']);
$manager->addEventHandler("main", "OnAdminTabControlBegin", [IBlockLocales::class, 'onAdminTabControlBegin']);
```

# Использование

Поддерживается перевод только текстовых поле:
- стандартные поля элемента инфолбока NAME, PREVIEW_TEXT, DETAIL_TEXT
- дополнительные свойства элемента инфоблока типов Строка и HTML/Текст

// todo
- стандартные поля раздела инфолбока
- дополнительные свойства раздела инфолбока

## Редактирование данных в админке

1. Открываем страницу редактирования элемента/раздела инфоблока
1. Открываем настройки формы редактирования
1. Жмём кнопку "Добавить" рядом со списком вкладок, вводим название вкладки "{{}}" (без кавычек, 2 открвающихся и 2 закрывающихся фигурных скобки)
1. Выбираем новую вкладку в спике и добавляем на неё те поля, для которых требуется выполнять перевод
1. Сохраняем настройки формы

В форме появятся вкладки с названиями "Перевод: ...", список языков для перевода берётся из языков, используемых в созданных в системе сайтах (Настройки - Настройки продукта - Сайты - Список сайтов. Открываем редактирование свойств сайта, блок "Региональные настройки", поле "Язык").

## Вывод данных в шаблонах

```
<?= \Sibirix\Translator\IBlockLocales::t($item['PROPERTY_TEXT_VALUE']) ?>
```

```$item['PROPERTY_TEXT_VALUE']``` - строка с данными в формате {{}}, сохранённая модутем в админке.

Выводит текст в языке текущего сайта (определяется по константе LANGUAGE_ID, [https://dev.1c-bitrix.ru/api_help/main/general/constants.php])