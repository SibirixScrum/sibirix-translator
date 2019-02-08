<?
/* @var $tabControl */
/* @var $prop */
/* @var $hidden */
/* @var $customFieldId */
/* @var $customFieldName */

$tabControl->BeginCustomField($customFieldId, $prop["NAME"], $prop["IS_REQUIRED"]==="Y");
?>
<tr id="tr_<?echo $customFieldId ?>"<?if ($prop["PROPERTY_TYPE"]=="F"):?> class="adm-detail-file-row"<?endif?>>
	<td class="adm-detail-valign-top" width="40%"><?if($prop["HINT"]!=""):
		?><span id="hint_<?echo $prop["ID"];?>"></span><script type="text/javascript">BX.hint_replace(BX('hint_<?echo $prop["ID"];?>'), '<?echo CUtil::JSEscape(htmlspecialcharsbx($prop["HINT"]))?>');</script>&nbsp;<?
	endif;?><?echo $tabControl->GetCustomLabelHTML();?>:</td>
	<td width="60%"><?_ShowPropertyField($customFieldName, $prop, $prop["VALUE"], false, false, 50000, $tabControl->GetFormName(), false);?></td>
</tr>
<?

$hidden = "";
if (!is_array($prop["~VALUE"])) {
    $values = [];
} else {
    $values = $prop["~VALUE"];
}

$start = 1;
foreach ($values as $key => $val) {
    if ($bCopy) {
        $key = "n" . $start;
        $start++;
    }

    if (is_array($val) && array_key_exists("VALUE", $val)) {
        $hidden .= _ShowHiddenValue($customFieldName . '[' . $key . '][VALUE]', $val["VALUE"]);
        $hidden .= _ShowHiddenValue($customFieldName . '[' . $key . '][DESCRIPTION]', $val["DESCRIPTION"]);
    } else {
        $hidden .= _ShowHiddenValue($customFieldName . '[' . $key . '][VALUE]', $val);
        $hidden .= _ShowHiddenValue($customFieldName . '[' . $key . '][DESCRIPTION]', "");
    }
}

$tabControl->EndCustomField($customFieldId, $hidden);
