<?
/* @var $tabControl */
/* @var $prop */
/* @var $hidden */
/* @var $customFieldId */
/* @var $customFieldName */

/** @global CUserTypeManager $USER_FIELD_MANAGER */
global $USER_FIELD_MANAGER;

$strLabel  = $prop['NAME'];
$fieldName = $prop['FIELD_NAME'];

if (is_array($prop['VALUE'])) {
    $prop['VALUE'] = reset($prop['VALUE']);
}

$tabControl->BeginCustomField($fieldName, $strLabel, $prop["MANDATORY"] == "Y");

echo $USER_FIELD_MANAGER->GetEditFormHTML(false, $prop['VALUE'], $prop);
$hidden = IBlockGetHiddenHTML($fieldName, '');

$tabControl->EndCustomField($fieldName, $hidden);
