<?
/* @var $tabControl */
/* @var $index */
/* @var $errorText */
/* @var $customFieldId */
/* @var $customFieldName */

$tabControl->BeginCustomField($customFieldId, $errorText, false);
?>
<tr id="tr_<?= $customFieldId ?>">
	<td colspan="2">
        <div class="adm-info-message-wrap adm-info-message-red">
            <div class="adm-info-message">
                <?= $errorText ?>
                <div class="adm-info-message-icon"></div>
            </div>
        </div>
    </td>
</tr>
<?
$tabControl->EndCustomField($customFieldId, $hidden);
