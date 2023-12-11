<?php 

declare(strict_types=1);

use GXModules\Library\Core\{
	Api\WebHooks\Service\WebhooksService,
	Api\WebHooks\Struct\WebHookRequest,
	Settings\Struct\Settings
};

use Coinsnap\Client\Webhook;
use Coinsnap\Result\TransactionState;
use GXModules\Shop\Classes\Model\CoinsnapTransactionModel;

class CoinsnapWebhookController extends HttpViewController {
	
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
    
    //  Check webhook signature to be a valid request.
    public function validWebhookRequest(string $signature, string $requestData): bool {
            
        if ($configuration === null) {
            $configuration = \MainFactory::create('CoinsnapStorage');
        }
        
        if ($secret = $configuration -> get('webhook_secret')) {
            return Webhook::isIncomingWebhookRequestValid($requestData, $signature, $secret);
        }
        
        return false;
    }

    public function actionIndex(){
        
        if($body = file_get_contents('php://input')){
            
            
            //  Validate webhook request.
            //  X-Coinsnap-Sig type: string
            //  HMAC signature of the body using the webhook's secret
            $headers = getallheaders();             
            
            $insertData = [
                'header' => json_encode($headers),
                'body' => $body,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            xtc_db_perform('coinsnap_callback', $insertData, 'insert');
            
                foreach ($headers as $key => $value) {
				if (strtolower($key) === 'x-coinsnap-sig') {
					$signature = $value;
				}
			}
            
            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
            if (isset($signature) && $this->validWebhookRequest($signature, $body)) {
                
                if (isset($data->invoiceId)) {
                    
                    $transactionModel = new CoinsnapTransactionModel();
                    
                    if($transaction = $transactionModel -> getFromDbByTransactionId($data->invoiceId)){
                        $orderId = (int)$transaction['order_id'];
                        $webhookType = $data->type;
                        $this->updateTransaction($orderId,$webhookType);
                    }
                    else {
                        die('No transaction found for this invoiceId.');
                    }
                }
                else {
                    die('No Coinsnap invoiceId provided, aborting.');
                }	
            }
            else {
                die('Webhook request validation failed.');
            }
            
            exit();
        }
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
