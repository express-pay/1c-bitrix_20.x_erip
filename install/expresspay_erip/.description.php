<?php

use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale\PaySystem;

Loc::loadMessages(__FILE__);

// Доверенный хост только из настроек; никакого fallback на заголовок Host
$currentHost = trim((string)\Bitrix\Main\Config\Option::get('main', 'server_name'));
if ($currentHost !== '' && !preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $currentHost)) {
	$currentHost = '';
}

// Если server_name не настроен, адрес по умолчанию не подставляем
$notificationUrl = '';
if ($currentHost !== '') {
	$notificationUrl = 'https://' . $currentHost . '/bitrix/tools/sale_ps_result.php';
}

$notificationWarning = '';
$signatureWarning = '';
if (!empty($_REQUEST['ID']) && Loader::includeModule('sale')) {
	$psId = (int)$_REQUEST['ID'];
	$dbRes = \CSalePaySystemAction::GetList([], ['PAY_SYSTEM_ID' => $psId]);
	$ps = $dbRes->Fetch();
	if (!empty($ps)) {
		$params = [];
		if (!empty($ps['PARAMS'])) {
			$params = is_array($ps['PARAMS']) ? $ps['PARAMS'] : unserialize($ps['PARAMS'], ['allowed_classes' => false]);
		}
		$savedUrl = $params['ERIP_NOTIFICATION_URL']['VALUE'] ?? '';
		if (!empty($savedUrl)) {
			$parsedUrl = parse_url($savedUrl);
			$savedHost = $parsedUrl['host'] ?? '';
			if (!empty($savedHost) && strtolower($savedHost) !== strtolower(preg_replace('/:\d+$/', '', $currentHost))) {
				$notificationWarning = '<div class="adm-info-message-wrap adm-info-message-red"><div class="adm-info-message"><div class="adm-info-message-title">' . Loc::getMessage('ERIP_NOTIFICATION_URL_WARNING') . '</div><div>' . Loc::getMessage('ERIP_NOTIFICATION_URL_WARNING_DETAIL', ['#URL#' => htmlspecialcharsbx($savedUrl), '#HOST#' => htmlspecialcharsbx($currentHost)]) . '</div></div></div>';
			}
		}
		$isUseSignatureValue = $params['ERIP_IS_USE_SIGNATURE_FROM_NOTIFICATION']['VALUE'] ?? null;
		$isUseSignature = ($isUseSignatureValue === 'Y' || $isUseSignatureValue === 1 || $isUseSignatureValue === '1') ? 'Y' : 'N';
		$secretWord = $params['ERIP_SECRET_WORD_FROM_NOTIFICATION']['VALUE'] ?? '';
		if ($isUseSignature === 'Y' && empty($secretWord)) {
			$signatureWarning = '<div class="adm-info-message-wrap adm-info-message-red"><div class="adm-info-message"><div class="adm-info-message-title">' . Loc::getMessage('ERIP_SIGNATURE_CONFIG_WARNING') . '</div><div>' . Loc::getMessage('ERIP_SIGNATURE_CONFIG_WARNING_DETAIL') . '</div></div></div>';
		}
		if ($isUseSignatureValue !== null && $isUseSignature !== 'Y') {
			$signatureWarning .= '<div class="adm-info-message-wrap adm-info-message-red"><div class="adm-info-message"><div class="adm-info-message-title">' . Loc::getMessage('ERIP_SIGNATURE_DISABLED_WARNING') . '</div></div></div>';
		}
	}
}

if (!empty($notificationWarning) || !empty($signatureWarning)) {
	echo '<div style="margin-bottom:15px;">';
	if (!empty($notificationWarning)) {
		echo $notificationWarning;
	}
	if (!empty($signatureWarning)) {
		echo $signatureWarning;
	}
	echo '</div>';
}

$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_TRUE;

$portalZone = Loader::includeModule('intranet') ? CIntranetUtils::getPortalZone() : '';
$licensePrefix = Loader::includeModule('bitrix24') ? \CBitrix24::getLicensePrefix() : '';

if (Loader::includeModule("bitrix24")) {
	if ($licensePrefix !== 'by') {
		$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_FALSE;
	}
} elseif (Loader::includeModule('intranet') && $portalZone !== 'ru') {
	$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_FALSE;
}

$description = Loc::getMessage('SALE_EXPRESSPAY_ERIP_DESCRIPTION');

$data = [
	'NAME' => Loc::getMessage('SALE_EXPRESSPAY_ERIP_TITLE'),
	'SORT' => 100,
	'IS_AVAILABLE' => $isAvailable,
	'CODES' => [
		"ERIP_IS_TEST_API" => [
			"SORT" => 10,
			"NAME" => Loc::getMessage("ERIP_IS_TEST_API_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_IS_TEST_API_DESCR"),
			"INPUT" => [
				'TYPE' => 'Y/N'
			],
			'DEFAULT' => [
				"PROVIDER_VALUE" => "N",
				"PROVIDER_KEY" => "INPUT"
			],
		],
		"ERIP_TOKEN"	=> [
			"SORT" => 20,
			"NAME"	=> Loc::getMessage("ERIP_TOKEN_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_TOKEN_DESCR"),
			"VALUE"	=> "",
			"TYPE"	=> ""
		],
		"ERIP_SERVICE_ID"	=> [
			"SORT" => 30,
			"NAME"	=> Loc::getMessage("ERIP_SERVICE_ID_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_SERVICE_ID_DESCR"),
			"VALUE"	=> "",
			"TYPE"	=> ""
		],
		"ERIP_IS_SHOW_QR_CODE" => [
			"SORT" => 40,
			"NAME" => Loc::getMessage("ERIP_IS_SHOW_QR_CODE_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_IS_SHOW_QR_CODE_DESCR"),
			"INPUT" => [
				'TYPE' => 'Y/N'
			],
			'DEFAULT' => [
				"PROVIDER_VALUE" => "N",
				"PROVIDER_KEY" => "INPUT"
			],
		],
		"ERIP_SECRET_WORD"	=> [
			"SORT" => 50,
			"NAME"	=> Loc::getMessage("ERIP_SECRET_WORD_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_SECRET_WORD_DESCR"),
			"DEFAULT" => [
				"PROVIDER_VALUE" => "",
				"PROVIDER_KEY" => "VALUE"
			],
		],
		"ERIP_NOTIFICATION_URL"	=> [
			"SORT" => 55,
			"NAME"	=> Loc::getMessage("ERIP_NOTIFICATION_URL_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_NOTIFICATION_URL_DESCR"),
			"DEFAULT" => [
				"PROVIDER_VALUE" => $notificationUrl,
				"PROVIDER_KEY" => "VALUE"
			],
		],
		"ERIP_IS_USE_SIGNATURE_FROM_NOTIFICATION" => [
			"SORT" => 60,
			"NAME" => Loc::getMessage("ERIP_IS_USE_SIGNATURE_FROM_NOTIFICATION_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_IS_USE_SIGNATURE_FROM_NOTIFICATION_DESCR"),
			"INPUT" => [
				'TYPE' => 'Y/N'
			],
			'DEFAULT' => [
				"PROVIDER_VALUE" => "Y",
				"PROVIDER_KEY" => "INPUT"
			],
		],
		"ERIP_SECRET_WORD_FROM_NOTIFICATION"	=> [
			"SORT" => 65,
			"NAME"	=> Loc::getMessage("ERIP_SECRET_WORD_FROM_NOTIFICATION_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_SECRET_WORD_FROM_NOTIFICATION_DESCR"),
			"DEFAULT" => [
				"PROVIDER_VALUE" => "",
				"PROVIDER_KEY" => "VALUE"
			],
		],
		"ERIP_ERIP_PATH"	=> [
			"SORT" => 80,
			"NAME"	=> Loc::getMessage("ERIP_ERIP_PATH_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_ERIP_PATH_DESCR"),
			"DEFAULT"	=> [
				"PROVIDER_VALUE" => Loc::getMessage("ERIP_ERIP_PATH_VALUE"),
				"PROVIDER_KEY" => "VALUE"
			],
		],
		"ERIP_IS_NAME_EDITABLE" => [
			"SORT" => 100,
			"NAME" => Loc::getMessage("ERIP_IS_NAME_EDITABLE_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_IS_NAME_EDITABLE_DESCR"),
			"INPUT" => [
				'TYPE' => 'Y/N'
			],
			'DEFAULT' => [
				"PROVIDER_VALUE" => "N",
				"PROVIDER_KEY" => "INPUT"
			],
		],
		"ERIP_IS_ADDRESS_EDITABLE" => [
			"SORT" => 105,
			"NAME" => Loc::getMessage("ERIP_IS_ADDRESS_EDITABLE_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_IS_ADDRESS_EDITABLE_DESCR"),
			"INPUT" => [
				'TYPE' => 'Y/N'
			],
			'DEFAULT' => [
				"PROVIDER_VALUE" => "N",
				"PROVIDER_KEY" => "INPUT"
			],
		],
		"ERIP_IS_AMOUNT_EDITABLE" => [
			"SORT" => 110,
			"NAME" => Loc::getMessage("ERIP_IS_AMOUNT_EDITABLE_NAME"),
			"DESCRIPTION"	=> Loc::getMessage("ERIP_IS_AMOUNT_EDITABLE_DESCR"),
			"INPUT" => [
				'TYPE' => 'Y/N'
			],
			'DEFAULT' => [
				"PROVIDER_VALUE" => "N",
				"PROVIDER_KEY" => "INPUT"
			],
		],
	]
];
