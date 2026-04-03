<?php 

declare(strict_types=1);

if (!defined('COINSNAP_SERVER_PATH')){
            define( 'COINSNAP_SERVER_PATH', 'stores' );  
        }
        

use GXModules\Library\Core\{
	Api\WebHooks\Service\WebhooksService,
	Api\WebHooks\Struct\WebHookRequest,
	Settings\Struct\Settings
};

use Coinsnap\Client\Webhook;
use Coinsnap\Result\TransactionState;
use GXModules\Shop\Classes\Model\CoinsnapTransactionModel;

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

    public function actionIndex(){
        
        if ($this->configuration === null) {
            $this->configuration = \MainFactory::create('CoinsnapStorage');
        }
        
        // First check if we have any input
        $rawPostData = file_get_contents('php://input');        
        
        if (!$rawPostData) {
            http_response_code(400);
            die('No raw post data received');
        } else {
            $this->save_log_record('info','Coinsnap Webhook Payload',$rawPostData);
        }
        
        $signature = null;
        $payloadKey = null;
        $_provider = ($this -> getProvider() === 'btcpay') ? 'btcpay' : 'coinsnap';
        
        // Get headers and check for signature
        
        $headers = getallheaders();
        
        $insertData = [
            'header' => json_encode($headers),
            'body' => $rawPostData,
            'created_at' => date('Y-m-d H:i:s')
        ];
            
        xtc_db_perform('coinsnap_callback', $insertData, 'insert');
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-coinsnap-sig' || strtolower($key) === 'btcpay-sig') {
                $signature = $value;
                $payloadKey = strtolower($key);
            }
        }

        // Handle missing or invalid signature
        if (!isset($signature)) {
            http_response_code(401);
            die('Authentication required');
        }

        // Validate the signature
        $webhook = json_decode($this->configuration->get('webhook'),true);
        
        if (!Webhook::isIncomingWebhookRequestValid($rawPostData, $signature, $webhook['secret'])) {
            $this->save_log_record('error','Invalid authentication signature',['payloadKey' => $payloadKey]);
            http_response_code(401);
            die('Invalid authentication signature for '.$payloadKey);
        }
        
        try {

            // Parse the JSON payload
            $postData = json_decode($rawPostData, false, 512, JSON_THROW_ON_ERROR);

            if (!isset($postData->invoiceId)) {
                http_response_code(400);
                die('No Coinsnap invoiceId provided');
            }

            if (strpos($postData->invoiceId, 'test_') !== false) {
                http_response_code(200);
                die('Successful webhook test');
            }

            $invoice_id = $postData->invoiceId;

            try {
                $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
                $csinvoice = $client->getInvoice($this->getStoreId(), $invoice_id);
                
                $status = $csinvoice->getData()['status'];
                $order_id = ($_provider === 'btcpay') ? $csinvoice->getData()['metadata']['orderId'] : $csinvoice->getData()['orderId'];
                $this->save_log_record('info','Coinsnap Webhook Payload',['Order Id' => $order_id,'Status' => $status]);
                $transactionModel = new CoinsnapTransactionModel();
                   
                if($transaction = $transactionModel -> getFromDbByTransactionId($invoice_id)){
                    $orderId = (int)$transaction['order_id'];
                    $this->updateTransaction($orderId,$status);
                }
                else {
                    $this->save_log_record('error','No transaction found for invoiceId',['Invoice Id' => $invoice_id]);
                    die('No transaction found for this invoiceId.');
                }

                echo "OK";
                exit;
            } catch (JsonException $e) {
                $this->save_log_record('error','Coinsnap Webhook Payload Error',['message' => $e->getMessage()]);
                http_response_code(400);
                die('Invalid JSON payload');
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            die('Internal server error');
        }
        exit();
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
            case TransactionState::NEW:
                $this->updateOrderAndTransactionStatus(TransactionState::NEW, $orderId);
                break;
            case TransactionState::EXPIRED:
		$this->updateOrderAndTransactionStatus(TransactionState::EXPIRED, $orderId);
		break;
            case TransactionState::SETTLED:
                $this->updateOrderAndTransactionStatus(TransactionState::SETTLED, $orderId);
                break;
            case TransactionState::PROCESSING:
                $this->updateOrderAndTransactionStatus(TransactionState::PROCESSING, $orderId);
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
