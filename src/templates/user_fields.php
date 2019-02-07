<?
/** @global CUserTypeManager $USER_FIELD_MANAGER */
global $USER_FIELD_MANAGER;

$strLabel = $prop['NAME'];
$FIELD_NAME = $prop['FIELD_NAME'];

if (is_array($prop['VALUE'])) {
    $prop['VALUE'] = reset($prop['VALUE']);
}

$tabControl->BeginCustomField($FIELD_NAME, $strLabel, $prop["MANDATORY"]=="Y");
    echo $USER_FIELD_MANAGER->GetEditFormHTML(false, $prop['VALUE'], $prop);
    $hidden = IBlockGetHiddenHTML($FIELD_NAME, '');
$tabControl->EndCustomField($FIELD_NAME, $hidden);
