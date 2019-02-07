<?
$tabControl->BeginCustomField($customFieldId, $prop["NAME"], $prop["IS_REQUIRED"]==="Y");

$hidden = "";
$hidden .= _ShowHiddenValue($customFieldName . '[n0][VALUE]', []);
$hidden .= _ShowHiddenValue($customFieldName . '[n0][DESCRIPTION]', "");

$tabControl->EndCustomField($customFieldId, $hidden);
