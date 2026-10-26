<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

?>
<?= Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_ERROR_DESCRIPTION') ?><b><?= htmlspecialcharsbx($params['message'] ?? Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_ERROR_GENERAL')) ?></b>