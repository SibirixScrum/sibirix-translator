<?

namespace Sibirix\Translator;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\Exception;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\LanguageTable;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SiteTable;
use Bitrix\Main\SystemException;

/**
 * Class IBlockLocales
 * @package Sibirix\Translator
 */
class IBlockLocales {
    private $id;
    private $type;
    private $iblockId;
    private $fieldsCode;

    private $languages = [];
    private $fields = [];
    private $properties = [];
    private $errors = [];
    private $tabControl;

    private $fieldTemplatesDir;

    /**
     * IBlockLocales constructor.
     * @throws SystemException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     */
    private function __construct() {
        $this->fieldTemplatesDir = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        $this->loadLangData();
    }

    /**
     * @return IBlockLocales
     * @throws SystemException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     */
    public static function getInstance() {
        static $instance;

        if (null === $instance) {
            $instance = new static();
        }

        return $instance;
    }

    /**
     * @throws SystemException
     */
    public static function onBeforeProlog() {
        self::getInstance()->beforeProlog();
    }

    /**
     * @param \CAdminTabControl $tabControl
     * @throws SystemException
     */
    public static function onAdminTabControlBegin(\CAdminTabControl $tabControl) {
        self::getInstance()->adminTabControlBegin($tabControl);
    }

    /**
     * @throws SystemException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     */
    protected function loadLangData() {
        $bxSites = new SiteTable();
        $languageCodes = [];

        $sites = $bxSites->getList([
            'group'  => ['LANGUAGE_ID'],
            'select' => ['LANGUAGE_ID'],
        ])->fetchAll();

        foreach ($sites as $site) {
            $languageCodes[] = $site['LANGUAGE_ID'];
        }

        if (empty($languageCodes)) {
            throw new Exception('No languages found');
        }

        $languageTable = new LanguageTable();
        $languageData = $languageTable->getList([
            'filter' => ['LID' => $languageCodes],
            'order'  => ['SORT' => 'asc']
        ])->fetchAll();

        foreach ($languageData as $language) {
            $this->languages[$language['LID']] = $language['NAME'];
        }
    }

    /**
     * @param \CAdminTabControl $tabControl
     * @throws SystemException
     */
    protected function adminTabControlBegin(\CAdminTabControl $tabControl) {
        $this->tabControl = $tabControl;

        $this->type = null;
        $bxRequest = Application::getInstance()->getContext()->getRequest();
        $requestedPage = $bxRequest->getRequestedPage();

        switch ($requestedPage) {
            case '/bitrix/admin/iblock_section_edit.php':
                $this->type = 'section';
                break;
            case '/bitrix/admin/iblock_element_edit.php':
                $this->type = 'element';
                break;
            default:
                return;
        }

        $tabs = $tabControl->tabs;

        $tmpTabs = [];
        $translateTab = false;
        foreach ($tabs as $tab) {
            if ('seo_adv_seo_adv' === $tab['DIV']) continue;

            if ('{{}}' == $tab['TAB']) {
                $translateTab = $tab;
                continue;
            }

            $tmpTabs[] = $tab;
        }

        // Для текущей страницы редактирования не задана вкладка переводов, выходим и ничего не делаем
        if (!$translateTab) return;

        $tabControl->tabs = $tmpTabs;

        $this->id = (int)$bxRequest->get('ID');
        $this->iblockId = (int)$bxRequest->get('IBLOCK_ID');

        $this->fieldsCode = array_map(function($field){ return $field['id']; }, $translateTab['FIELDS']);

        switch ($this->type) {
            case 'section':
                $this->prepareForSection();
                break;
            case 'element':
                $this->prepareForElement();
                break;
        }

        // На вкладку переводов не добавлены никакие поля, которые мы можем обработать
        // Возвращаем не изменённый таб и выходим
        if (0 == count($this->properties) && 0 == count($this->fields)) {
            $tabControl->tabs[] = $translateTab;
            return;
        }

        $tabControl->tabIndex = count($tabControl->tabs) - 1;
        foreach ($this->fields as $field) {
            if ('Y' === $field['IS_REQUIRED']) {
                $field['VALUE'] = [];
                $field['~VALUE'] = [];
                $this->addField($tabControl, $field, true);
            }
        }

        foreach ($this->properties as $index => $field) {
            if ($field['USER_TYPE_ID'] == 'string' && $field['SETTINGS']['ROWS'] > 1) {
                $this->properties[$index] = [
                    'ID' => $field['XML_ID'],
                    'IBLOCK_ID' => 4,
                    'NAME' => $field['EDIT_FORM_LABEL'],
                    'CODE' => $field['XML_ID'],
                    'DEFAULT_VALUE' => '',
                    'PROPERTY_TYPE' => 'S',
                    'ROW_COUNT' => '1',
                    'COL_COUNT' => '30',
                    'LIST_TYPE' => 'L',
                    'MULTIPLE' => 'N',
                    'IS_REQUIRED' => 'N',
                    'VERSION' => '2',
                    'USER_TYPE' => 'HTML',
                    'USER_TYPE_SETTINGS' => [
                        'height' => 200
                    ],
                    'VALUE_TYPE' => 'html',
                    'VALUES_BY_LANG' => $field['VALUES_BY_LANG']
                ];
            }
        }

        // todo ???
        $eventMeta = [
            'MESSAGE_ID' => "sibirix.base",
            'FROM_MODULE_ID' => "adminTabAddField"
        ];

        // todo ???
        $list = EventManager::getInstance()->findEventHandlers("sibirix.base", "OnAdminTabAddFields" . "_" . $this->iblockId . "_" . $this->type);
        $list = array_map(function($item) use ($eventMeta) { return array_merge($item, $eventMeta); }, $list);

        // Добавляем в форму табы для каждого языка
        foreach ($this->languages as $langCode => $langName) {
            $tab = [
                'DIV'    => 'lang-' . $langCode,
                'TAB'    => 'Перевод: ' . $langName,
                'ICON'   => 'iblock_element',
                'TITLE'  => 'Перевод: ' . $langName,
                'FIELDS' => [],
            ];

            $tabControl->tabs[] = $tab;
            $tabControl->tabIndex = count($tabControl->tabs) - 1;

            foreach ($this->fields as $originField) {
                $field = $originField;

                $field['CODE'] = $langCode . '_' . $field['CODE'];
                $field['ID'] = $langCode . '_' . $field['ID'];

                $this->addField($tabControl, $this->parseValue($field, $langCode));
            }

            foreach ($this->properties as $originField) {
                $field = $originField;

                $field['CODE'] = $langCode . '_' . $field['CODE'];
                $field['ID'] = 'LOC_' . $langCode . '_' . $field['ID'];
                $field['FIELD_NAME'] = $field['ID'];

                $this->addField($tabControl, $this->parseValue($field, $langCode));
            }

            foreach ($this->errors as $errorText) {
                $this->addError($tabControl, $errorText);
            }

            foreach ($list as $arEvent) {
                ExecuteModuleEventEx($arEvent, [$tabControl, $langCode, $this->id]);
            }
        }
    }

    /**
     * @param $field
     * @param $lang
     * @return mixed
     */
    protected function parseValue($field, $lang) {
        if (!isset($field['VALUES_BY_LANG']) || !isset($field['VALUES_BY_LANG'][$lang])) {
            return $field;
        }

        $value = $field['VALUES_BY_LANG'][$lang];

        if (isset($field['VALUE_TYPE'])) {
            $field['VALUE'] = [
                'n0' => ['TEXT' => $value, 'TYPE' => $field['VALUE_TYPE']]
            ];

            $field['~VALUE'] = [
                'n0' => ['TEXT' => $value, 'TYPE' => $field['VALUE_TYPE']]
            ];
        } else {
            $field['VALUE'] = [
                'n0' => $value
            ];

            $field['~VALUE'] = [
                'n0' => $value
            ];
        }

        return $field;
    }

    /**
     * todo ???
     */
    protected function prepareForSection() {
        if (0 === $this->iblockId && 0 !== $this->id) {
            $el = \CIBlockSection::GetByID($this->id)->Fetch();
            $this->iblockId = (int)$el['IBLOCK_ID'];
        }

        if (0 === $this->iblockId) {
            die('not set iblock id');
        }

        $properties = [];
        $fields = [];

        $propertiesCode = array_filter($this->fieldsCode, function ($field) {
            return 'UF_' === substr($field, 0, 3);
        });

        $entityId = "IBLOCK_" . $this->iblockId . "_SECTION";

        /** @global \CUserTypeManager $USER_FIELD_MANAGER */
        global $USER_FIELD_MANAGER;
        $arUserFields = $USER_FIELD_MANAGER->GetUserFields($entityId, $this->id, LANGUAGE_ID);

        foreach ($arUserFields as $arUserField) {
            if (!in_array($arUserField['FIELD_NAME'], $propertiesCode)) {
                continue;
            }

            // Можем обработыть только тестовые поля
            if ($arUserField['USER_TYPE_ID'] !== 'string') {
                $this->errors[] = 'Невозможно добавить перевод для свойства: ' . $arUserField['FIELD_NAME'];
                continue;
            }

            $arUserField['NAME'] = $arUserField['EDIT_FORM_LABEL'];
            $arUserField['CODE'] = $arUserField['FIELD_NAME'];
            $arUserField['ID'] = $arUserField['FIELD_NAME'];
            $properties[] = $arUserField;
        }

        $fieldsIblock = [];
        // Из стандартных свойств раздела инфоблока работаем только с Названием и Описанием
        if (in_array('NAME', $this->fieldsCode)
            || in_array('DESCRIPTION', $this->fieldsCode)) {
            $fieldsIblock = \CIBlock::GetFields($this->iblockId);
        }

        if (in_array('NAME', $this->fieldsCode)) {
            $fields[] = [
                'ID'                 => 'NAME',
                'IBLOCK_ID'          => $this->iblockId,
                'NAME'               => $fieldsIblock['SECTION_NAME']['NAME'],
                'CODE'               => 'NAME',
                'DEFAULT_VALUE'      => '',
                'PROPERTY_TYPE'      => 'S',
                'ROW_COUNT'          => '1',
                'COL_COUNT'          => '30',
                'LIST_TYPE'          => 'L',
                'MULTIPLE'           => 'N',
                'IS_REQUIRED'        => $fieldsIblock['SECTION_NAME']['IS_REQUIRED'],
                'VERSION'            => '2',
                'USER_TYPE'          => null,
                'USER_TYPE_SETTINGS' => null,
            ];
        }

        if (in_array('DESCRIPTION', $this->fieldsCode)) {
            $fields[] = [
                'ID'                 => 'DESCRIPTION',
                'IBLOCK_ID'          => $this->iblockId,
                'NAME'               => $fieldsIblock['SECTION_DESCRIPTION']['NAME'],
                'CODE'               => 'DESCRIPTION',
                'DEFAULT_VALUE'      => '',
                'PROPERTY_TYPE'      => 'S',
                'ROW_COUNT'          => '1',
                'COL_COUNT'          => '30',
                'LIST_TYPE'          => 'L',
                'MULTIPLE'           => 'N',
                'IS_REQUIRED'        => $fieldsIblock['SECTION_DESCRIPTION']['IS_REQUIRED'],
                'VERSION'            => '2',
                'USER_TYPE'          => 'HTML',
                'USER_TYPE_SETTINGS' => ['height' => 200],
            ];
        }

        // Если редактируем раздел (передан ID раздела) - заполняем значения полей
        if ($this->id) {
            $elRes = \CIBlockSection::GetByID($this->id);

            if (0 !== count($fields)) {
                $elFields = $elRes->Fetch();
                foreach ($fields as $index => $field) {
                    if (isset($elFields[$field['CODE'] . '_TYPE'])) {
                        $fields[$index]['VALUE_TYPE'] = $elFields[$field['CODE'] . '_TYPE'];
                    }

                    $fields[$index]['VALUES_BY_LANG'] = $this->valuesByLang(['VALUE' => $elFields[$field['CODE']]]);
                }
            }

            foreach ($properties as $index => $pr) {
                $properties[$index]['VALUES_BY_LANG'] = $this->valuesByLang($properties[$index]);
            }
        }

        $this->fields = $fields;
        $this->properties = $properties;
    }

    /**
     *
     */
    protected function prepareForElement() {
        if (0 === $this->iblockId && 0 !== $this->id) {
            $el = \CIBlockElement::GetByID($this->id)->Fetch();
            $this->iblockId = (int)$el['IBLOCK_ID'];
        }

        if (0 === $this->iblockId) {
            die('not set iblock id');
        }

        $properties = [];
        $fields = [];

        $propertiesId = array_map(function($field){
            return strpos($field, 'PROPERTY_') === 0 ? substr($field, 9) : false;
        }, $this->fieldsCode);
        $propertiesId = array_filter($propertiesId);

        // Пробуем обработать все дополнительные свойства инфоблока
        if (count($propertiesId)) {
            $res = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $this->iblockId]);
            while ($property = $res->Fetch()) {
                $properties[] = $property;
            }

            $errors = [];
            $properties = array_filter($properties, function($property) use ($propertiesId, &$errors) {
                if (!in_array($property['ID'], $propertiesId)) {
                    return false;
                }
                // Можем обработыть только тестовые поля
                if ($property['PROPERTY_TYPE'] !== 'S') {
                    $errors[] = 'Невозможно добавить перевод для свойства: ' . $property['NAME'];
                    return false;
                }

                if ($property['PROPERTY_TYPE'] == 'S') {
                    // Из кастомных типов полей обрабатываем только HTML/текст
                    if (!empty($property['USER_TYPE']) && $property['USER_TYPE'] != 'HTML') {
                        $errors[] = 'Невозможно добавить перевод для свойства: ' . $property['NAME'];
                        return false;
                    }
                }
                return true;
            });
            $this->errors = $errors;
        }

        // Из стандартных свойств инфоблока работаем только с Названием, Текстом для анонса и Подробным описанием
        $fieldsIblock = [];
        if (in_array('NAME', $this->fieldsCode)
            || in_array('PREVIEW_TEXT', $this->fieldsCode)
            || in_array('DETAIL_TEXT', $this->fieldsCode)) {
            $fieldsIblock = \CIBlock::GetFields($this->iblockId);
        }

        foreach ($this->fieldsCode as $fieldCode) {
            if (strpos($fieldCode, 'PROPERTY_') === 0) continue;

            if ($fieldCode === 'NAME') {
                $fields[] = [
                    'ID'                 => 'NAME',
                    'IBLOCK_ID'          => $this->iblockId,
                    'NAME'               => $fieldsIblock['NAME']['NAME'],
                    'CODE'               => 'NAME',
                    'DEFAULT_VALUE'      => '',
                    'PROPERTY_TYPE'      => 'S',
                    'ROW_COUNT'          => '1',
                    'COL_COUNT'          => '30',
                    'LIST_TYPE'          => 'L',
                    'MULTIPLE'           => 'N',
                    'IS_REQUIRED'        => $fieldsIblock['NAME']['IS_REQUIRED'],
                    'VERSION'            => '2',
                    'USER_TYPE'          => null,
                    'USER_TYPE_SETTINGS' => null
                ];
            } elseif (in_array($fieldCode, ['PREVIEW_TEXT', 'DETAIL_TEXT'])) {
                $fields[] = [
                    'ID'                 => $fieldCode,
                    'IBLOCK_ID'          => $this->iblockId,
                    'NAME'               => $fieldsIblock[$fieldCode]['NAME'],
                    'CODE'               => $fieldCode,
                    'DEFAULT_VALUE'      => '',
                    'PROPERTY_TYPE'      => 'S',
                    'ROW_COUNT'          => '1',
                    'COL_COUNT'          => '30',
                    'LIST_TYPE'          => 'L',
                    'MULTIPLE'           => 'N',
                    'IS_REQUIRED'        => $fieldsIblock[$fieldCode]['IS_REQUIRED'],
                    'VERSION'            => '2',
                    'USER_TYPE'          => 'HTML',
                    'USER_TYPE_SETTINGS' => ['height' => 200],
                ];
            } else {
                $this->errors[] = 'Невозможно добавить перевод для поля: ' . $fieldCode;
            }
        }

        // Если редактируем элемент (передан ID элемента) - заполняем значения полей
        if ($this->id) {
            $elRes = \CIBlockElement::GetByID($this->id);
            $el = $elRes->GetNextElement();

            if (0 !== count($fields)) {
                // Обрабатываем значения стардантных полей
                $elFields = $el->GetFields();
                foreach ($fields as $index => $field) {
                    if (isset($elFields[$field['CODE'] . '_TYPE'])) {
                        $fields[$index]['VALUE_TYPE'] = $elFields[$field['CODE'] . '_TYPE'];
                    }

                    $fields[$index]['VALUES_BY_LANG'] = $this->valuesByLang(['VALUE' => $elFields['~' . $field['CODE']]]);
                }
            }

            // Обрабатываем значения дополнительных полей
            $elementProperties = $el->GetProperties([], ['ID' => $propertiesId]);
            foreach ($properties as $index => $pr) {
                $property = $elementProperties[$pr['CODE']];
                $properties[$index]['VALUES_BY_LANG'] = $this->valuesByLang($property);
                if (is_array($property['VALUE']) && isset($property['VALUE']['TYPE'])) {
                    $properties[$index]['VALUE_TYPE'] = $property['VALUE']['TYPE'];
                }
            }
        }

        $this->fields = $fields;
        $this->properties = $properties;
    }

    /**
     * @param $property
     * @return array
     */
    protected function valuesByLang($property) {
        if (false === is_array($property['VALUE'])) {
            $property['VALUE'] = [
                $property['VALUE']
            ];
        }

        $valueKey = key($property['VALUE']);
        $value = $property['VALUE'][$valueKey];

        $parseValue = $value;
        if (is_array($value) && isset($value['TEXT'])) {
            $parseValue = $value['TEXT'];
        }

        return static::translateParseData($parseValue);
    }

    /**
     * @param $raw
     * @return mixed
     */
    public function unPackIblockFormSettings($raw) {
        return array_reduce(
            explode('--;--', trim($raw['tabs'], "-;")),
            function($candy, $item) {
                $candy[] = array_map(function($it) {
                    return explode('--#--', $it);
                }, explode('--,--', $item));

                return $candy;
            },
            []
        );
    }

    /**
     * @param $iblockId
     * @param $type
     * @return bool|mixed
     */
    public function getForm($iblockId, $type) {
        $settings = \CUserOptions::GetOption('form', 'form_' . $type . '_' . $iblockId, null, false);
        if ($settings) {
            return $this->unPackIblockFormSettings($settings);
        }

        return false;
    }

    /**
     * @param $tabControl
     * @param $prop
     * @param bool $hidden
     */
    protected function addField($tabControl, $prop, $hidden = false) {
        // Эти 2 переменные используются внутри include'ящихся файлов
        /** @noinspection PhpUnusedLocalVariableInspection */
        $customFieldId   = $prop["ID"];
        /** @noinspection PhpUnusedLocalVariableInspection */
        $customFieldName = 'LOC_' . $prop["ID"];

        if (isset($prop['ENTITY_ID'])) {
            include ($this->fieldTemplatesDir . 'user_fields.php');
        } else {
            if ($hidden) {
                include ($this->fieldTemplatesDir . 'prop_common_hidden.php');
            } else {
                include ($this->fieldTemplatesDir . 'prop_common.php');
            }
        }

        $newField = $prop["ID"];
        $tabControl->arFields[$newField] = $tabControl->tabs[$tabControl->tabIndex]['FIELDS'][$newField];
    }

    /**
     * @param $tabControl
     * @param $errorText
     */
    protected function addError($tabControl, /** @noinspection PhpUnusedParameterInspection */ $errorText) {
        // Эти 2 переменные (+ параметр $errorText) используются внутри include'ящихся файлов
        /** @noinspection PhpUnusedLocalVariableInspection */
        $customFieldId = 1000000 + rand(1, 1000000); // todo
        /** @noinspection PhpUnusedLocalVariableInspection */
        $customFieldName = 'LOC_' . $customFieldId;

        include ($this->fieldTemplatesDir . 'error_text.php');

        $newField = $customFieldId;
        $tabControl->arFields[$newField] = $tabControl->tabs[$tabControl->tabIndex]['FIELDS'][$newField];
    }

    /**
     * Обработка пришедших данных при сохранении формы редактирования
     * @throws SystemException
     */
    protected function beforeProlog() {
        // Обрабатывать только внутри админки
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) return;

        $bxRequest = Application::getInstance()->getContext()->getRequest();
        $requestedPage = $bxRequest->getRequestedPage();
        if ('POST' !== $bxRequest->getRequestMethod()) return;

        if ('/bitrix/admin/iblock_section_edit.php' === $requestedPage) {

            $this->type = 'section';
            $this->iblockId = $_REQUEST['IBLOCK_ID'];

            $form = $this->getForm($this->iblockId, $this->type);
            // Форма не закастомлена, выходим без обработки
            if (empty($form)) return;

            $tab = array_pop($form);
            $element = array_shift($tab);
            if ($element[1] !== '{{}}') {
                return;
            }

            $mergeFields = [];

            foreach ($bxRequest->getPostList() as $name => $value) {
                $nameTokens = explode('_', $name);
                $nameTokens = array_values(array_filter($nameTokens));

                preg_match('/PROP_(.+)_(.+)__n0__VALUE__(.+)_/', $name, $matches);
                if (!empty($matches) && $matches[3] == 'TEXT') {
                    $nameTokens = [
                        'LOC',
                        $matches[1],
                        $matches[2],
                    ];
                }

                if ('LOC' === $nameTokens[0]) {
                    if ('UF' === $nameTokens[2]) {
                        $nameTokens[2] .= '_' . $nameTokens[3];
                    }
                    if (!is_array($value)) {
                        $mergeFields[$nameTokens[1]][$nameTokens[2]] = $value;
                    } else {
                        $mergeFields[$nameTokens[1]][$nameTokens[2]] = reset($value);
                    }
                }

                if ('PROP' === $nameTokens[0]
                    && is_numeric($nameTokens[1])
                    && in_array($nameTokens[2], ['DESCRIPTION'])
                ) {
                    if (in_array($nameTokens[2], ['DESCRIPTION'])) {
                        if ('TYPE' === $nameTokens[5]) {
                            $mergeFields[$nameTokens[1]][$nameTokens[2] . '_TYPE'] = $value;
                        } else {
                            $mergeFields[$nameTokens[1]][$nameTokens[2]] = $value;
                        }
                    }
                }
            }

            $mergedFields = [];
            foreach ($mergeFields as $langId => $fields) {
                foreach ($fields as $name => $value) {
                    if (in_array($name, ['DESCRIPTION_TYPE'])) {
                        $mergedFields[$name] = $value; // TODO check type
                        continue;
                    }

                    $mergedFields[$name][] = $langId . ':' . $value;
                }
            }


            $fields = array_map(function($item) {
                return $item[0];
            }, $tab);

            foreach ($mergedFields as $name => $values) {
                if (is_array($values)) {
                    $mergedFields[$name] = '{{' . implode('}}{{', $values) . '}}';
                }
            }
            foreach ($mergedFields as $fieldName => $value) {
                if (in_array($fieldName, $fields)) {
                    $GLOBALS[$fieldName] = $value;
                    $_POST[$fieldName] = $value;
                }
            }

            return;
        }

        if ('/bitrix/admin/iblock_element_edit.php' === $requestedPage) {
            $this->type = 'element';
            $this->iblockId = $_REQUEST['IBLOCK_ID'];
            $mergeFields = [];
            $mergeProperties = [];

            $form = $this->getForm($this->iblockId, $this->type);
            // Форма не закастомлена, выходим без обработки
            if (empty($form)) return;

            $tab = array_pop($form);
            $element = array_shift($tab);
            if ($element[1] !== '{{}}') {
                return;
            }

            foreach ($bxRequest->getPostList() as $name => $value) {
                $nameTokens = explode('_', $name);
                $nameTokens = array_values(array_filter($nameTokens));

                if ('LOC' === $nameTokens[0] && 'LOC' === $nameTokens[1]) {
                    if (is_numeric($nameTokens[3])) {
                        $mergeProperties[$nameTokens[2]][$nameTokens[3]] = reset($value);
                    } else {
                        $mergeFields[$nameTokens[2]][$nameTokens[3]] = reset($value);
                    }

                } else if ('LOC' === $nameTokens[0]) {
                    if (is_numeric($nameTokens[2])) {
                        $mergeProperties[$nameTokens[1]][$nameTokens[2]] = reset($value);
                    } else {
                        $mergeFields[$nameTokens[1]][$nameTokens[2]] = reset($value);
                    }
                }

                if ('PROP' === $nameTokens[0] && 'LOC' === $nameTokens[1]) {
                    array_splice($nameTokens, 1, 1);
                }
                if ('PROP' === $nameTokens[0] && isset($this->languages[$nameTokens[1]])
                    && (is_numeric($nameTokens[2])
                        || (in_array($nameTokens[2] . '_' . $nameTokens[3], ['PREVIEW_TEXT', 'DETAIL_TEXT'])))
                ) {

                    if (in_array($nameTokens[2] . '_' . $nameTokens[3], ['PREVIEW_TEXT', 'DETAIL_TEXT'])) {
                        if ('TYPE' === $nameTokens[6]) {
                            $mergeFields[$nameTokens[1]][$nameTokens[2] . '_' . $nameTokens[3] . '_TYPE'] = $value;
                        } else {
                            $mergeFields[$nameTokens[1]][$nameTokens[2] . '_' . $nameTokens[3]] = $value;
                        }
                    } else {
                        if ('TYPE' === $nameTokens[5]) {
                            $mergeProperties[$nameTokens[1]][$nameTokens[2]]['TYPE'] = $value;
                        } else {
                            $mergeProperties[$nameTokens[1]][$nameTokens[2]]['TEXT'] = $value;
                        }
                    }
                }
            }

            $mergedFields = [];
            foreach ($mergeFields as $langId => $fields) {
                foreach ($fields as $name => $value) {
                    if (in_array($name, ['PREVIEW_TEXT_TYPE', 'DETAIL_TEXT_TYPE'])) {
                        $mergedFields[$name] = $value; // TODO check type
                        continue;
                    }

                    $mergedFields[$name][] = $langId . ':' . $value;
                }
            }

            foreach ($mergedFields as $name => $values) {
                if (is_array($values)) {
                    $mergedFields[$name] = '{{' . implode('}}{{', $values) . '}}';

                    // todo проверку на превышение длинны строки для varchar
                }
            }

            $mergedProperties = [];
            foreach ($mergeProperties as $langId => $fields) {
                foreach ($fields as $id => $value) {
                    if (is_array($value) && isset($value['TEXT'])) {
                        $mergedProperties[$id]['TEXT'][] = $langId . ':' . $value['TEXT'];
                        if (!isset($mergedProperties[$id]['TYPE'])) {
                            $mergedProperties[$id]['TYPE'] = $value['TYPE'];
                        }
                        continue;
                    }

                    $mergedProperties[$id][] = $langId . ':' . $value;
                }
            }

            foreach ($mergedProperties as $name => $values) {
                if (is_array($values) && isset($values['TEXT'])) {
                    $values['TEXT'] = '{{' . implode('}}{{', $values['TEXT']) . '}}';
                    $mergedProperties[$name] = $values;
                    continue;
                }

                if (is_array($values)) {
                    $mergedProperties[$name] = '{{' . implode('}}{{', $values) . '}}';
                }
            }

            $propertyNames = array_map(function($item) {
                return $item[0];
            }, $tab);

            $properties = array_filter($propertyNames, function($item) {
                $t = 'PROPERTY_';
                return substr($item, 0, strlen($t)) == $t;
            });

            $fields = array_filter($propertyNames, function($item) {
                $t = 'PROPERTY_';
                return substr($item, 0, strlen($t)) != $t;
            });

            foreach ($mergedProperties as $propId => $value) {
                if (in_array('PROPERTY_' . $propId, $properties)) {
                    $_POST['PROP'][$propId] = $value;
                }
            }

            foreach ($mergedFields as $fieldName => $value) {
                if (in_array($fieldName, $fields)) {
                    $_POST[$fieldName] = $value;
                }
            }
        }
    }

    /**
     * @param $string
     * @return array
     */
    public static function translateParseData($string) {
        $result = preg_match_all('#(?<={{)([a-z]{2}):([\w\s\S]*)(?=}})#mU', $string, $matches, PREG_SET_ORDER);
        if ($result === false || $result == 0 || empty($matches)) {
            return [LANGUAGE_ID => $string];
        }

        $langContent = [];
        foreach ($matches as $lang) {
            $langContent[$lang[1]] = $lang[2];
        }

        return $langContent;
    }

    /**
     * Парсит входящую строку на предмет вхождения языкового контента и выдает нужный вариант
     * @param string $str
     * @return string
     */
    public static function translate($str = '') {
        $langContent = static::translateParseData($str);
        $curLang = LANGUAGE_ID;

        if (isset($langContent[$curLang])) {
            return $langContent[$curLang];
        }

        return trim($langContent['default']);
    }

    /**
     * Короткий алиас на метод перевода
     * @param string $str
     * @return mixed
     */
    public static function t($str = '') {
        return static::t($str);
    }
}