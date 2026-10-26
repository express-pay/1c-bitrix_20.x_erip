<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$sum = round($params['sum'], 2);
$order_id = $params['order_id'];
$erip_path = $params['erip_path'];
$show_qr_code = $params['show_qr_code'];
$qr_code = $params['qr_code'];
?>

<table style="width: 100%;text-align: left;">
	<tbody>
		<tr>
			<td valign="top" style="text-align:left;">
				<?= Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_DESCRIPTION') ?>
				<br /> <?= Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_DESCRIPTION_ONE') ?>
				<br /><b><?= htmlspecialcharsbx($erip_path) ?></b>
				<br /> <?= Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_DESCRIPTION_TWO', ['##ORDER_ID##' => htmlspecialcharsbx($order_id)]) ?>
				<br /> <?= Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_DESCRIPTION_THREE', ['##SUM##' => $sum]) ?>
			</td>
			<?php if ($show_qr_code) : ?>
				<td style="text-align: center;padding: 0px 20px 0 0;vertical-align: middle">
					<img src="data:image/jpeg;base64,<?= htmlspecialcharsbx(preg_replace('/[^A-Za-z0-9+\/=\r\n]/', '', $qr_code)) ?>" width="200" height="200" />
					<p><b><?= Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_DESCRIPTION_QR_CODE')?></b></p>
				</td>
			<?php endif; ?>
		</tr>
	</tbody>
</table>