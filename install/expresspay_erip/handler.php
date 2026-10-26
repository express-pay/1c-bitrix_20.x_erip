<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main,
	Bitrix\Main\Web\HttpClient,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale\PaySystem,
	Bitrix\Main\Request,
	Bitrix\Sale\Payment,
	Bitrix\Sale\PaySystem\ServiceResult,
	Bitrix\Sale\PaymentCollection,
	Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

/**
 * Class ExpressPay_EripHandler
 * @package Sale\Handlers\PaySystem
 */
class ExpressPay_EripHandler extends PaySystem\ServiceHandler
{
	private const API_URL = 'https://api.express-pay.by/v1/';
	private const TEST_API_URL = 'https://sandbox-api.express-pay.by/v1/';

	private const SEND_METHOD_HTTP_POST = "POST";
	private const SEND_METHOD_HTTP_GET = "GET";

	private const CHECKOUT_TEMPLATE = "checkout";
	private const ERROR_TEMPLATE = "error";

	/**
	 * 
	 * Главная функция в обработчике, в ней можно, например, 
	 * добавить к параметрам обработчика, которые задаются 
	 * в административной части, какие-то дополнительные, 
	 * и вызвать шаблон, который должен находится в подпапке 
	 * template (перечень параметров обработчика платежей, 
	 * задаваемых в админке, задается в файле 
	 * .description.php, его структуру вы можете также изучить 
	 * по исходным кодам системных обработчиков). 
	 * Сам шаблон можно скопировать в шаблон сайта в 
	 * подпапку payment/название_папки_обработчика/template/.
	 * 
	 * @param Payment $payment
	 * @param Request|null $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	public function initiatePay(Payment $payment, Request $request = null): ServiceResult
	{
		$request_params = $this->getInvoiceParam($payment);

		if ($this->isTestMode($payment)) {
			$url = self::TEST_API_URL . "web_invoices";
		} else {
			$url = self::API_URL . "web_invoices";
		}

		$result = $this->send(self::SEND_METHOD_HTTP_POST, $url, $request_params);

		if (isset($result->getData()['Errors'])) {
			PaySystem\Logger::addDebugInfo(__CLASS__ . ': API errors: ' . print_r($result->getData()['Errors'], 1));
			$this->setExtraParams(['message' => Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_ERROR_GENERAL')]);
		} else {
			$this->setExtraParams($this->getTemplateParams($payment, $result->getData()));
		}

		$showTemplateResult = $this->showTemplate($payment, isset($result->getData()['Errors']) ? self::ERROR_TEMPLATE : self::CHECKOUT_TEMPLATE);

		if ($showTemplateResult->isSuccess()) {
			$result->setTemplate($showTemplateResult->getTemplate());
		} else {
			$result->addErrors($showTemplateResult->getErrors());
		}

		return $result;
	}

	/**
	 * 
	 * Функция getIndicativeFields должна вернуть массив полей, 
	 * по которым проверяется принадлежность информации при возврате 
	 * к данному обработчику, при этом функция может вернуть 
	 * как ассоциативный массив (т.е. с символьными ключами), 
	 * при этом проверка будет производится по значениям полей, 
	 * так и неассоциативный - при этом проверка будет производится 
	 * только по наличию полей в $request.
	 * 
	 */
	public static function getIndicativeFields()
	{
    	return array('Data');
	}

	/**
	 * Формирование массива значений для шаблона
	 * 
	 * @param Payment $payment   Объект платежа
	 * @param array   $response  Массив значений полученный при выставлении счёта
	 * 
	 * @return array  $params Массив значений
	 * 
	 */
	private function getTemplateParams(Payment $payment, $response): array
	{

		$params = [
			'sum' => (string)(PriceMaths::roundPrecision($payment->getSum())),
			'erip_path' => $this->getBusinessValue($payment, 'ERIP_ERIP_PATH'),
			'order_id' => $response['ExpressPayAccountNumber'],
			'show_qr_code' => $this->isShowQrCode($payment),
			'qr_code' => $this->getQrCode($payment, $response['ExpressPayInvoiceNo'])
		];

		return $params;
	}

	/**
	 * 
	 * Получения Qr-кода
	 * 
	 * @param int $invoice_no Номер счета полученый в процессе выставления его выставления
	 * 
	 * @return object $result Объект полученный при десерриализации JSON
	 * 
	 */
	private function getQrCode(Payment $payment, $invoice_no)
	{
		$params = array(
			"Token" => $this->getBusinessValue($payment, 'ERIP_TOKEN'),
			"InvoiceId" => $invoice_no,
			'ViewType' => 'base64'
		);

		if ($this->isTestMode($payment)) {
			$url = 'https://sandbox-api.express-pay.by/v1/qrcode/getqrcode/?';
		} else {
			$url = 'https://api.express-pay.by/v1/qrcode/getqrcode/?';
		}

		$params["Signature"] = $this->computeSignature($params, $payment, 'get_qr_code');

		$result = $this->send(self::SEND_METHOD_HTTP_GET, $url . http_build_query($params));

		return $result->getData()['QrCodeBody'];
	}

	/**
	 * 
	 * Получаем параметры для заполнения формы 
	 * 
	 * @return array $request_params Параметры запроса 
	 * 
	 */
	private function getInvoiceParam(Payment $payment)
	{
		$collection = $payment->getCollection();
		$order = $collection->getOrder();
		$userEmail = $order->getPropertyCollection()->getUserEmail();

		if ($this->is_test == "Y")
			$order_id = 100;
		else
			$order_id = IntVal($payment->getId());

		$out_summ = number_format(floatval($payment->getSum()), 2, ',', '');

		$serviceId = $this->getBusinessValue($payment, 'ERIP_SERVICE_ID');
		$info = "Оплата заказа № " . $order_id;
		$name_edit = $this->getBusinessValue($payment, 'ERIP_IS_NAME_EDITABLE');
		$address_edit = $this->getBusinessValue($payment, 'ERIP_IS_ADDRESS_EDITABLE');
		$amount_edit = $this->getBusinessValue($payment, 'ERIP_IS_AMOUNT_EDITABLE');

		// Доверенный хост только из настроек; никакого fallback на заголовок Host
		$currentHost = trim((string)\Bitrix\Main\Config\Option::get('main', 'server_name'));

		if ($currentHost === '' || !preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $currentHost)) {
			PaySystem\Logger::addDebugInfo(__CLASS__ . ': main:server_name is not configured — cannot build trusted return URL');
			throw new Main\SystemException('main:server_name is not configured');
		}

		$protocol = 'https://';

		// Несовпадение Host — это отказ, а не просто запись в лог
		$requestHost = $_SERVER['HTTP_HOST'] ?? '';
		if ($requestHost !== '' && strcasecmp($requestHost, $currentHost) !== 0) {
			PaySystem\Logger::addDebugInfo(sprintf(
				'%s: Host mismatch rejected. HTTP_HOST=%s, trusted_host=%s',
				__CLASS__, $requestHost, $currentHost
			));
			throw new Main\SystemException('Host header does not match the configured site host');
		}

		$path = '/bitrix/tools/sale_ps_result.php';

		$existingParams = [];
		$returnParams = array_merge($existingParams, ['result' => 'success', 'ExpressPayAmount' => $out_summ]);
		$failParams = array_merge($existingParams, ['result' => 'fail']);

		$returnUrl = $protocol . $currentHost . $path . '?' . http_build_query($returnParams);
		$failUrl = $protocol . $currentHost . $path . '?' . http_build_query($failParams);

		$request_params = array(
			'ServiceId'         => $serviceId,
			'AccountNo'         => $order_id,
			'Amount'            => $out_summ,
			'Currency'          => 933,
			'ReturnType'        => 'json',
			'ReturnUrl'         => $returnUrl,
			'FailUrl'           => $failUrl,
			'Expiration'        => '',
			'Info'              => $info,
			'Surname'           => '',
			'FirstName'         => '',
			'Patronymic'        => '',
			'Street'            => '',
			'House'             => '',
			'Apartment'         => '',
			'IsNameEditable'    => $name_edit == 'Y' ? 1 : 0,
			'IsAddressEditable' => $address_edit == 'Y' ? 1 : 0,
			'IsAmountEditable'  => $amount_edit == 'Y' ? 1 : 0,
			'EmailNotification' => ($userEmail) ? $userEmail->getValue() : '',
			'SmsPhone'          => ''
		);

		$request_params['Signature'] = $this->computeSignature($request_params, $payment);

		return $request_params;
	}

	/**
	 * Формирование цифровой подписи
	 * 
	 * @param array  $request_params Параметры запроса
	 * @param string $method Метод API
	 * 
	 * @return string $hash Полученный хеш
	 */
	private function computeSignature($request_params, Payment $payment, $method = 'add_invoice')
	{
		$secret_word = trim($this->getSecretWord($payment));
		$normalized_params = array_change_key_case($request_params, CASE_LOWER);
		$api_method = array(
			'add_invoice' => array(
				"serviceid",
				"accountno",
				"amount",
				"currency",
				"expiration",
				"info",
				"surname",
				"firstname",
				"patronymic",
				"city",
				"street",
				"house",
				"building",
				"apartment",
				"isnameeditable",
				"isaddresseditable",
				"isamounteditable",
				"emailnotification",
				"smsphone",
				"returntype",
				"returnurl",
				"failurl",
				"returninvoiceurl"
			),
			'get_qr_code' => array(
				"invoiceid",
				"viewtype",
				"imagewidth",
				"imageheight"
			),
			'add_invoice_return' => array(
				"accountno",
				"invoiceno"
			)
		);

		$result = $this->getToken($payment);

		foreach ($api_method[$method] as $item)
			$result .= (isset($normalized_params[$item])) ? $normalized_params[$item] : '';

		$hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

		return $hash;
	}

	/**
	 * @param string $method HTTP-метод
	 * @param string $url Адрес запроса
	 * @param array $params Параметры запроса
	 * @param array $headers Заголовки запроса
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function send(string $method, string $url, array $params = [], array $headers = []): ServiceResult
	{
		$result = new ServiceResult();

		$httpClient = new HttpClient();
		foreach ($headers as $name => $value) {
			$httpClient->setHeader($name, $value);
		}

		if ($method === self::SEND_METHOD_HTTP_GET) {
			$response = $httpClient->get($url);
		} else {
			PaySystem\Logger::addDebugInfo(__CLASS__ . ': request data: ' . print_r([
				'AccountNo' => $params['AccountNo'] ?? null,
				'Amount'    => $params['Amount'] ?? null,
				'Currency'  => $params['Currency'] ?? null,
				'ServiceId' => $params['ServiceId'] ?? null,
			], 1));

			$response = $httpClient->post($url, $params);
		}

		if ($response === false) {
			$errors = $httpClient->getError();
			foreach ($errors as $code => $message) {
				$result->addError(PaySystem\Error::create($message, $code));
			}

			return $result;
		}

		PaySystem\Logger::addDebugInfo(__CLASS__ . ': response received, length: ' . strlen((string)$response));

		$response = static::decode($response);
		if ($response) {
			$result->setData($response);
		} else {
			$result->addError(PaySystem\Error::create($response));
		}

		return $result;
	}

	/**
	 * Получение списка валют
	 * 
	 * @return array|string[]
	 */
	public function getCurrencyList(): array
	{
		return ['BYN'];
	}

	/**
	 * Создание оплаты
	 * 
	 * @param Payment $payment
	 * @param Request $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	public function processRequest(Payment $payment, Request $request): ServiceResult
	{
		$result = new ServiceResult();

		$rawData   = (string)$request->getPost("Data");
		$signature = trim((string)$request->getPost("Signature"));

		$data = static::decode($rawData);

		if (!is_array($data)) {
			$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_INVALID_NOTIFICATION_DATA')));
			return $result;
		}

		$cmdType = filter_var($data['CmdType'] ?? null, FILTER_VALIDATE_INT);
		$status  = filter_var($data['Status'] ?? null, FILTER_VALIDATE_INT);

		if ($cmdType === false || $status === false) {
			$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_INVALID_NOTIFICATION_DATA')));
			return $result;
		}

		// Подпись проверяется ВСЕГДА — fail-closed
		$computedSignature = $this->computeNotifSignature($rawData);

		if ($computedSignature === null) {
			PaySystem\Logger::addDebugInfo(__CLASS__ . ': ' . Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_EMPTY_SECRET_CONFIG_ERROR'));
			$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_EMPTY_SECRET_CONFIG_ERROR')));
			return $result;
		}

		if ($signature === '' || !hash_equals($computedSignature, strtoupper($signature))) {
			PaySystem\Logger::addDebugInfo(sprintf(
				'%s: rejected unsigned/invalid notification for payment %d',
				__CLASS__, $payment->getId()
			));
			$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_WRONG_SIGNATURE')));
			return $result;
		}

		if ($cmdType === 3 && ($status === 3 || $status === 6)) {

			$notificationAccountNo = filter_var($data['AccountNo'] ?? null, FILTER_VALIDATE_INT);
			if ($notificationAccountNo === false || $notificationAccountNo !== $payment->getId()) {
				PaySystem\Logger::addDebugInfo(sprintf(
					'%s: AccountNo mismatch. Notification=%s, Payment=%d',
					__CLASS__, var_export($data['AccountNo'] ?? null, true), $payment->getId()
				));
				$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_PAYMENT_MISMATCH')));
				return $result;
			}

			if ($payment->isPaid()) {
				$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_PAYMENT_ALREADY_PAID')));
				return $result;
			}

			$notificationAmount = PriceMaths::roundPrecision((float)str_replace(',', '.', $data['Amount']));
			$expectedSum = PriceMaths::roundPrecision($payment->getSum());
			if ($notificationAmount !== $expectedSum) {
				$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_AMOUNT_MISMATCH')));
				return $result;
			}

			$result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
			$result->setPsData($this->getPaymentField($data));
		}

		return $result;
	}

	/**
	 * 
	 * Формирование полей опалаты
	 * 
	 * @param array $data Параметры уведомления
	 * 
	 * @return array $fields Поля опалаты
	 * 
	 */
	private function getPaymentField($data): array
	{
		$payer = isset($data['Payer']) && is_scalar($data['Payer']) ? (string)$data['Payer'] : '';
		$payer = htmlspecialcharsbx(mb_substr($payer, 0, 255));

		$amount = PriceMaths::roundPrecision((float)str_replace(',', '.', (string)($data['Amount'] ?? '')));
		$accountNo = (string)($data['AccountNo'] ?? '');

		$description = Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_PAYMENT_DESCRIPTION', [
			'#ID#' => $accountNo,
			'#Payer#' => $payer,
		]);

		$fields = [
			"PS_STATUS" => "Y",
			"PS_STATUS_CODE" => (int)($data['Status'] ?? 0),
			"PS_STATUS_DESCRIPTION" => ((int)($data['Status'] ?? 0) === 6) ? Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_PAYMENT_STATUS_DESCRIPTION_CARD') : Loc::getMessage('SALE_HPS_EXPRESSPAY_ERIP_PAYMENT_STATUS_DESCRIPTION'),
			"PS_STATUS_MESSAGE" => $description,
			"PS_SUM" => number_format($amount, 2, '.', ''),
			"PS_CURRENCY" => 'BYN',
			"PS_RESPONSE_DATE" => new \Bitrix\Main\Type\DateTime(),
			"PAY_VOUCHER_NUM" => $accountNo,
			"PAY_VOUCHER_DATE" => new \Bitrix\Main\Type\Date()
		];
		
		return $fields;
	}

	/**
	 * Формирование цифровой подписи для уведомления
	 * 
	 * @param string $json Полученное уведомление
	 * 
	 * @return string $hash Полученный хеш
	 */
	private function computeNotifSignature($json)
	{
		$secretWord = $this->getSecretWordForNotif();
		if (empty($secretWord)) {
			return null;
		}
		return strtoupper(hash_hmac('sha1', $json, $secretWord));
	}

	/**
	 * 
	 * Получение ID оплаты при получении уведомления 
	 * 
	 * @param Request $request
	 * @return bool|int|mixed
	 */
	public function getPaymentIdFromRequest(Request $request)
	{
		$data = $request->getPost("Data");
		$data = static::decode($data);

		if (isset($data)) {
				return (int)$data['AccountNo'];
			}
		return false;
	}

	/**
	 * @param Payment $payment
	 * @return mixed|string
	 */
	private function getToken(Payment $payment)
	{
		return $this->getBusinessValue($payment, 'ERIP_TOKEN');
	}

	/**
	 * @param Payment $payment
	 * @return mixed|string
	 */
	private function isShowQrCode(Payment $payment): bool
	{
		return ($this->getBusinessValue($payment, 'ERIP_IS_SHOW_QR_CODE') === 'Y');
	}

	/**
	 * @param Payment $payment
	 * @return mixed|string
	 */
	private function getSecretWord(Payment $payment)
	{
		return $this->getBusinessValue($payment, 'ERIP_SECRET_WORD');
	}

	/**
	 * @param Payment $payment
	 * @return bool
	 */
	protected function isTestMode(Payment $payment = null): bool
	{
		return ($this->getBusinessValue($payment, 'ERIP_IS_TEST_API') === 'Y');
	}

	/**
	 * @param Payment $payment
	 * @return mixed|string
	 */
	private function getSecretWordForNotif(Payment $payment = null)
	{
		return $this->getBusinessValue($payment, 'ERIP_SECRET_WORD_FROM_NOTIFICATION');
	}

	/**
	 * @param string $data
	 * @return mixed
	 */
	private static function decode($data)
	{
		try {
			return Main\Web\Json::decode($data);
		} catch (Main\ArgumentException $exception) {
			return false;
		}
	}
}
