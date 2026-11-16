<?php 

declare(strict_types=1);


if (file_exists(dirname(__DIR__) . '/../../loader.php')) {
    require_once dirname(__DIR__) . '/../../loader.php';
}
if (!defined('COINSNAP_SERVER_PATH')){
            define( 'COINSNAP_SERVER_PATH', 'stores' );  
        }
        

use GXModules\Library\Core\{
	Api\WebHooks\Service\WebhooksService,
	Api\WebHooks\Struct\WebHookRequest,
	Settings\Struct\Settings
};

use GXModules\Shop\Classes\Model\CoinsnapTransactionModel;
use GXModules\Library\Webhook\InvoiceDecision;
use GXModules\Library\Webhook\InvoiceRecord;
use GXModules\Library\Webhook\Signature;

class CoinsnapWebhookController extends HttpViewController {
	
    public $configuration;   //  @var $configuration
    protected $webHooksService; //  @var WebhooksService $webHooksService
    public $settings;           //  @var Settings $settings

    /**
	* @param HttpContextReaderInterface $httpContextReader
	* @param HttpResponseProcessorInterface $httpResponseProcessor
	* @param ContentViewInterface $defaultContentView
   
    public function __construct(HttpContextReaderInterface $httpContextReader, HttpResponseProcessorInterface $httpResponseProcessor, ContentViewInterface $defaultContentView){
        
        $this->configuration = MainFactory::create('CoinsnapStorage');
        $this->settings = new Settings();
        parent::__construct($httpContextReader, $httpResponseProcessor, $defaultContentView);
        
    } */
    
    public function getProvider() {
        
        return ($this->configuration->get('provider') === 'btcpay') ? 'btcpay' : 'coinsnap';
    }        
            
    public function getApiUrl() {
        return ($this->getProvider() === 'btcpay')? $this->configuration->get('btcpay_serverurl') : 'https://app.coinsnap.io';
    }	

    public function getStoreId() {
        return ($this->getProvider() === 'btcpay')? $this->configuration->get('btcpay_storeid') : $this->configuration->get('storeid');
    }	
    
    public function getApiKey() {
        return ($this->getProvider() === 'btcpay')? $this->configuration->get('btcpay_apikey') : $this->configuration->get('apikey');
    }

    // Webhook endpoint. Only the invoice id is taken from the request; every decision comes from the API.
    public function actionIndex(){

        if ($this->configuration === null) {
            $this->configuration = \MainFactory::create('CoinsnapStorage');
        }

        $rawPostData = (string) file_get_contents('php://input');
        $webhook = json_decode((string) $this->configuration->get('webhook'), true);
        $secret = is_array($webhook) ? (string) ($webhook['secret'] ?? '') : '';

        $problem = ($this->getProvider() === 'btcpay')
            ? Signature::btcpayProblem($this->requestHeader('BTCPay-Sig'), $rawPostData, $secret)
            : Signature::coinsnapProblem($this->requestHeader('X-Coinsnap-Signature'), $rawPostData, $secret, time());
        if ($problem !== null) {
            $this->logSecurity($problem);
            $this->reply(401, 'Unauthorized');
        }

        $body = json_decode($rawPostData, true);
        $invoiceId = (is_array($body) && is_string($body['invoiceId'] ?? null)) ? $body['invoiceId'] : '';
        if ($invoiceId === '') {
            $this->reply(200, 'OK');
        }
        $claimsPaid = in_array($body['type'] ?? '', ['Settled', 'InvoiceSettled'], true);

        try {
            $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
            $invoice = $client->getInvoice($this->getStoreId(), $invoiceId)->getData();
        } catch (\Coinsnap\Exception\RequestException $e) {
            if (in_array($e->getCode(), [400, 404], true)) {
                // The dashboard's "Send test webhook" button lands here.
                $this->logInfo('unknown invoice', $invoiceId);
                $this->reply(200, 'OK');
            }
            $this->logInfo('retry: API unavailable', $invoiceId);
            $this->reply(503, 'Retry');
        } catch (\Throwable $e) {
            $this->logInfo('retry: API unavailable', $invoiceId);
            $this->reply(503, 'Retry');
        }

        // Only invoices this module created have a transaction row, which names the order.
        $transaction = $this->transactions()->getFromDbByTransactionId($invoiceId);
        if (empty($transaction['order_id'])) {
            $this->logInfo('not ours', $invoiceId);
            $this->reply(200, 'OK');
        }
        $orderId = (int) $transaction['order_id'];
        $expected = $this->invoiceRecord()->find($invoiceId, $orderId) ?? $this->legacyExpected($transaction);

        $decision = InvoiceDecision::decide($invoice, $this->getProvider() === 'btcpay', (string) $expected['amount'], (string) $expected['currency']);
        if ($decision !== InvoiceDecision::PAID && ($claimsPaid || $decision === InvoiceDecision::MISMATCH)) {
            $this->logSecurity($claimsPaid ? 'forged status' : 'amount mismatch', $invoiceId);
            $this->reply(200, 'OK');
        }

        $this->applyDecision($decision, (string) ($transaction['state'] ?? ''), $orderId, $invoiceId, $expected);
        $this->reply(200, 'OK');
    }

    protected function reply(int $status, string $message): void
    {
        http_response_code($status);
        die($message);
    }

    private function requestHeader(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
    }

    protected function transactions(): CoinsnapTransactionModel
    {
        return new CoinsnapTransactionModel();
    }

    protected function invoiceRecord(): InvoiceRecord
    {
        return new InvoiceRecord();
    }

    // Orders placed before 1.2.0 have no record; check against what was sent then.
    private function legacyExpected(array $transaction): array
    {
        $order = json_decode((string) ($transaction['data'] ?? ''), true);
        $info = is_array($order['info'] ?? null) ? $order['info'] : [];
        return ['amount' => number_format((float) ($info['total'] ?? 0), 2, '.', ''), 'currency' => (string) ($info['currency'] ?? '')];
    }

    // Moves an order forward only, judged by the state this module last recorded for the invoice.
    private function applyDecision(string $decision, string $state, int $orderId, string $invoiceId, array $expected): void
    {
        $new = CoinsnapTransactionModel::TRANSACTION_STATE_NEW;
        $processing = CoinsnapTransactionModel::TRANSACTION_STATE_PROCESSING;
        $expired = CoinsnapTransactionModel::TRANSACTION_STATE_EXPIRED;

        if ($decision === InvoiceDecision::PAID) {
            // A late payment can still settle an order that expired.
            if (!in_array($state, [$new, $processing, $expired, ''], true)) {
                $this->logInfo('no change from ' . $state, $invoiceId);
                return;
            }
            if (!$this->invoiceRecord()->claimPaid($invoiceId, $orderId, (string) $expected['amount'], (string) $expected['currency'])) {
                $this->logInfo('already processed', $invoiceId);
                return;
            }
            $this->updateOrderAndTransactionStatus(CoinsnapTransactionModel::TRANSACTION_STATE_SETTLED, $orderId);
            $this->logInfo('paid', $invoiceId);
            return;
        }
        if ($decision === InvoiceDecision::PROCESSING && in_array($state, [$new, ''], true)) {
            $this->updateOrderAndTransactionStatus($processing, $orderId);
            $this->logInfo('status processing', $invoiceId);
            return;
        }
        if ($decision === InvoiceDecision::EXPIRED && in_array($state, [$new, $processing, ''], true)) {
            $this->updateOrderAndTransactionStatus($expired, $orderId);
            $this->logInfo('status expired', $invoiceId);
            return;
        }
        $this->logInfo('no change', $invoiceId);
    }

    private function logInfo(string $reason, string $invoiceId = ''): void
    {
        $this->writeLog('info', $this->webhookLine('INFO', $reason, $invoiceId));
    }

    // At most 10 security lines a minute, so a flood of fake requests can't fill the log.
    private function logSecurity(string $reason, string $invoiceId = ''): void
    {
        $minute = gmdate('YmdHi');
        $stored = explode(':', (string) $this->configuration->get('seclog'));
        $count = ($stored[0] === $minute) ? (int) ($stored[1] ?? 0) + 1 : 1;
        $this->configuration->set('seclog', $minute . ':' . $count);
        if ($count === 11) {
            $this->writeLog('warning', 'Coinsnap SECURITY webhook: more rejections this minute, not logged');
        }
        if ($count <= 10) {
            $this->writeLog('warning', $this->webhookLine('SECURITY', $reason, $invoiceId));
        }
    }

    private function webhookLine(string $level, string $reason, string $invoiceId): string
    {
        // The invoice id comes from the request.
        $id = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceId), 0, 64);
        return sprintf('Coinsnap %s webhook reason=%s invoice=%s', $level, $reason, $id !== '' ? $id : '-');
    }

    // Gambio's log when available; a logging problem never stops the webhook.
    protected function writeLog(string $level, string $line): void
    {
        try {
            \LogControl::get_instance()->notice($line, 'payment', 'coinsnap', $level === 'warning' ? 'warning' : 'notice');
        } catch (\Throwable $e) {
            error_log($line);
        }
    }

    //  string $mode: error | info | debug | warning
    public function save_log_record($mode,$title,$message){
        //$languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);        
        //$logger = \Gambio\Core\Application::getInstance()->getLogger();
        //$logger->$mode($title, $message);
    }

    
    
    /**
	 * @param int $orderId
	 * @param string $webhookType
	 * @throws Exception
     */
    private function updateTransaction(int $orderId, string $webhookType): void {
        
        switch ($webhookType){
            case CoinsnapTransactionModel::TRANSACTION_STATE_NEW:
                $this->updateOrderAndTransactionStatus(CoinsnapTransactionModel::TRANSACTION_STATE_NEW, $orderId);
                break;
            case CoinsnapTransactionModel::TRANSACTION_STATE_EXPIRED:
		$this->updateOrderAndTransactionStatus(CoinsnapTransactionModel::TRANSACTION_STATE_EXPIRED, $orderId);
		break;
            case CoinsnapTransactionModel::TRANSACTION_STATE_SETTLED:
                $this->updateOrderAndTransactionStatus(CoinsnapTransactionModel::TRANSACTION_STATE_SETTLED, $orderId);
                break;
            case CoinsnapTransactionModel::TRANSACTION_STATE_PROCESSING:
                $this->updateOrderAndTransactionStatus(CoinsnapTransactionModel::TRANSACTION_STATE_PROCESSING, $orderId);
                break;
        }
        
        /*  Let's leave it for the future
        //
        if ($this->settings->isConfirmationEmailSendEnabled()) {
            $this->sendOrderConfirmationEmail($orderId);
        }*/
    }
    
    /**
	 * @param string $newStatus
	 * @param int $orderId
	 * @throws Exception
     */
    private function updateOrderAndTransactionStatus(string $newStatus, int $orderId): void {
        $this->updateOrderStatus($newStatus, $orderId);
        $transactionModel = new CoinsnapTransactionModel();
        $transactionModel->updateTransactionStatus($newStatus, $orderId);
    }
    
    
    

    /**
     * Let's leave it for the future
         * @param int $orderId
	 */
	private function sendOrderConfirmationEmail(int $orderId): void
	{
		$order = new order($orderId);
		$t_mail_attachment_array = [];
		$t_payment_info_html = '';
		$t_payment_info_text = '';
		$t_mail_logo = '';
		$t_logo_mail = MainFactory::create_object('GMLogoManager', array("gm_logo_mail"));
		if ($t_logo_mail->logo_use == '1') {
			$t_mail_logo = $t_logo_mail->get_logo();
		}
		$additionalOrderData = xtc_db_query("SELECT `language` FROM orders WHERE orders_id='" . xtc_db_input($orderId) . "'");
		$orderLanguage = xtc_db_fetch_array($additionalOrderData);

		$additionalLanguageData = xtc_db_query("SELECT `languages_id`, `code`  FROM languages WHERE LOWER(name)='" . strtolower(addslashes($orderLanguage['language'])) . "'");
		$languageData = xtc_db_fetch_array($additionalLanguageData);

		$coo_send_order_content_view = MainFactory::create_object('SendOrderContentView');
		$coo_send_order_content_view->set_('order', $order);
		$coo_send_order_content_view->set_('order_id', $orderId);
		$coo_send_order_content_view->set_('language', $orderLanguage['language']);
		$coo_send_order_content_view->set_('language_id', $languageData['languages_id']);
		$coo_send_order_content_view->set_('language_code', $languageData['code']);
		$coo_send_order_content_view->set_('payment_info_html', $t_payment_info_html);
		$coo_send_order_content_view->set_('payment_info_text', $t_payment_info_text);
		$coo_send_order_content_view->set_('mail_logo', $t_mail_logo);

		$t_mail_content_array = $coo_send_order_content_view->get_mail_content_array();
		$t_content_mail = $t_mail_content_array['html'];
		$t_txt_mail = $t_mail_content_array['txt'];

		// CREATE SUBJECT
		if (extension_loaded('intl')) {
			$order_date = utf8_encode_wrapper(DateFormatter::formatAsFullDate(new DateTime(), new LanguageCode(new StringType($languageData['code']))));
		} else {
			$order_date = utf8_encode_wrapper(strftime(DATE_FORMAT_LONG));
		}

		$t_subject = gm_get_content('EMAIL_BILLING_SUBJECT_ORDER', $languageData['languages_id']);
		if (empty($t_subject)) {
			$t_subject = EMAIL_BILLING_SUBJECT_ORDER;
		}

		$order_subject = str_replace('{$nr}', (string)$orderId, $t_subject);
		$order_subject = str_replace('{$date}', $order_date, $order_subject);
		$order_subject = str_replace('{$lastname}', $order->customer['lastname'], $order_subject);
		$order_subject = str_replace('{$firstname}', $order->customer['firstname'], $order_subject);

		xtc_php_mail(EMAIL_BILLING_ADDRESS,
			EMAIL_BILLING_NAME,
			$order->customer['email_address'],
			$order->customer['firstname'] . ' ' . $order->customer['lastname'],
			'',
			EMAIL_BILLING_REPLY_ADDRESS,
			EMAIL_BILLING_REPLY_ADDRESS_NAME,
			$t_mail_attachment_array,
			'',
			$order_subject,
			$t_content_mail,
			$t_txt_mail
		);
	}

	/**
	 * @param TransactionInvoice $transactionInvoice
	 * @param int $orderId
	 * @throws Exception
	 */
	private function updateTransactionInvoice(TransactionInvoice $transactionInvoice, int $orderId): void
	{
		switch ($transactionInvoice->getState()) {
			case TransactionInvoiceState::DERECOGNIZED:
				$this->updateOrderAndTransactionStatus(TransactionInvoiceState::DERECOGNIZED, $orderId);
				break;

			case TransactionInvoiceState::NOT_APPLICABLE:
			case TransactionInvoiceState::PAID:
				$this->updateOrderAndTransactionStatus(TransactionInvoiceState::PAID, $orderId);
				break;
		}
	}

	

	/**
	 * @param string $newStatus
	 * @param int $orderId
	 * @throws Exception
	 */
	private function updateOrderStatus(string $newStatus, int $orderId): void
	{
		/** @var OrderWriteServiceInterface $orderWriteService */
		$orderWriteService = StaticGXCoreLoader::getService('OrderWrite');
		$orderStatusId = $this->getOrderStatusId($newStatus);
		$orderWriteService->updateOrderStatus(
			new IdType($orderId),
			new IntType($orderStatusId),
			new StringType(''),
			new BoolType(false)
		);
	}

	/**
	 * @param string $currentOrderStatusName
	 * @return int
	 * @throws Exception
	 */
	private function getOrderStatusId(string $currentOrderStatusName): int
	{
		$orderStatusId = 0;
		$orderStatusService = StaticGXCoreLoader::getService('OrderStatus');
		/** @var \OrderStatusInterface $orderStatus */
		foreach ($orderStatusService->findAll() as $orderStatus) {
			$orderStatusName = $orderStatus->getName(MainFactory::create('LanguageCode', new StringType('en')));
			if (strtolower($orderStatusName) === strtolower($currentOrderStatusName)) {
				$orderStatusId = $orderStatus->getId();
				break;
			}
		}

		return $orderStatusId;
	}
}
