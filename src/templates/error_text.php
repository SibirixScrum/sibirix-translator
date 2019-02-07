<?
$tabControl->BeginCustomField($customFieldId, $errorText, false);
?>
<tr id="tr_<?echo $customFieldId ?>"<?if ($prop["PROPERTY_TYPE"]=="F"):?> class="adm-detail-file-row"<?endif?>>
	<td class="adm-detail-valign-top" width="40%"></td>
	<td width="60%"><?= $errorText ?></td>
</tr>
<?
$tabControl->EndCustomField($customFieldId, $hidden);
