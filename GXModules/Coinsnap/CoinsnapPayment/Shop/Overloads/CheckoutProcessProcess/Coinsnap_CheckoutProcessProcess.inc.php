<?php

use GXModules\Library\{
    Core\Settings\Struct\Settings,
    Helper\CoinsnapHelper
};

use GXModules\Shop\Classes\Model\CoinsnapTransactionModel;
use Gambio\Core\Application;

//use Coinsnap\Model\{AddressCreate, LineItemCreate, LineItemType, Transaction, TransactionCreate};
//use Coinsnap\Model\TransactionPending;

class Coinsnap_CheckoutProcessProcess extends Coinsnap_CheckoutProcessProcess_parent{

    protected $configuration;
    protected $languageTextManager;
    protected $logger;
    
//  The proceed method is the main method of the class and performs the complete checkout process.
    public function proceed(){//:bool
        
        $this->configuration = MainFactory::create('CoinsnapStorage');
        
        if (strpos($_SESSION['payment'] ?? '', 'coinsnap') === false) {
            parent::proceed();
	}
		
	$_SESSION['gambio_hub_selection'] = $_SESSION['payment'];
	if ($this->check_redirect()) {
            return true;
	}
                
        $this->_initOrderData();

	// check if tmp order id exists
	//if (!isset($_SESSION['tmp_oID']) || !is_int($_SESSION['tmp_oID'])) {
            $this->save_order();

            $this->save_module_data();
            $this->coo_order_total->apply_credit();
            $this->process_products();
            $this->save_tracking_data();

            // redirect to payment service
            if ($this->tmp_order) {
                $this->coo_payment->payment_action();
            }
	//}
        /*
        if ($this->tmp_order === false) {
            $settings = new Settings();
            $this->coo_payment->after_process();
            //$this->set_redirect_url(xtc_href_link("shop.php", 'do=CoinsnapPayment/PaymentPage', 'SSL'));
            return true;
        }*/
        
        if(isset($_SESSION['invoiceURL'])){
            $this->set_redirect_url($_SESSION['invoiceURL']);
        }
                
        return true;
    }
    
    public function checkAmount($amount, $currency){
        
        $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
        $checkInvoice = [];

        try {
            if ($this->getProvider() === 'btcpay') {
                try {
                    $storePaymentMethods = $store->getStorePaymentMethods($this->getStoreId());
                    
                    $this->save_log_record('info','Store Payment Methods',$storePaymentMethods);
                    
                    if ($storePaymentMethods['code'] === 200 && !isset($storePaymentMethods['result']['error'])){
                        if (!$storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                            $errorMessage = 'No payment method is configured on BTCPay server';
                            $checkInvoice = array('result' => false,'error' => $errorMessage);
                        }
                    }
                    else {
                        $errorMessage = 'Error store loading. Wrong or empty Store ID';
                        $checkInvoice = array('result' => false,'error' => $errorMessage);
                    }

                    if( isset($storePaymentMethods['result']['error']) ){
                        $errorMessage = 'Error data handling ('.$storePaymentMethods['result']['error'].')';
                        $checkInvoice = array('result' => false,'error' => $errorMessage);
                    }
                    elseif ($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                        $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency), 'bitcoin');
                    } elseif ($storePaymentMethods['result']['lightning']) {
                        $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency), 'lightning');
                    }
                } catch (\Throwable $e) {
                    $errorMessage = 'API connection is not established';
                    $checkInvoice = array('result' => false,'error' => $errorMessage);
                }
            } else {
                $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency));
            }
        } catch (\Throwable $e) {
            $errorMessage = 'API connection is not established';
            $checkInvoice = array('result' => false,'error' => $errorMessage);
        }
        return $checkInvoice;
    }
        
    public function getInvoiceUrl($order_id){        
		
        $order = (array)$this->coo_order;
        
        $amount = number_format($order['info']['total'],2);
        $currency = $order['info']['currency'];
	$buyerName = $order['customer']['firstname'].' '.$order['customer']['lastname'];
	$buyerEmail = $order['customer']['email_address'];
        
        $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $checkInvoice = $this->checkAmount($amount, strtoupper($currency));
        
        if ($checkInvoice['result'] === true) {
            $metadata = [];
            $metadata['orderNumber'] = $order_id;
            $metadata['customerName'] = $buyerName;
            
            $redirectUrl = xtc_href_link("checkout_success.php");
            
            if ($this->getProvider() === 'btcpay') {
                $metadata['orderId'] = $order_id;
            }
            
            $redirectAutomatically = ($this->configuration->get('autoredirect') > 0) ? true : false;
            $walletMessage = '';
            
            // Handle currencies non-supported by BTCPay Server, we need to change them BTC and adjust the amount.
            if ($currency !== 'BTC' && $this->getProvider() === 'btcpay') {
                $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
                $btcpayCurrencies = $store -> getStoreCurrenciesRates($this->getStoreId(),array($currency));
                $isCurrency = true;
                if(!isset($btcpayCurrencies['result']['error']) && count($btcpayCurrencies['result']['currencies'])>0){
                    if(!isset($btcpayCurrencies['result']['currencies']['BTC_'.$currency])){
                        $isCurrency = false;
                    }
                }
                else {
                    $isCurrency = false;
                }
                    
                // Handle currencies non-supported by BTCPay Server, we need to change them to BTC and adjust the amount.
                if( !$isCurrency ){
                    $currency = 'BTC';
                    $rate = 1/$checkInvoice['rate'];
                    $amountBTC = bcdiv(strval($amount), strval($rate), 8);
                    $amount = (float)$amountBTC;
                }
            }
        
            $camount = ($currency === 'BTC')? \Coinsnap\Util\PreciseNumber::parseFloat($amount,8) : \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);
            
            $invoice = $client->createInvoice(
                $this->getStoreId(),  
                $currency,
		$camount,
		$order_id,
		$buyerEmail,
		$buyerName, 
		$redirectUrl,
		COINSNAP_REFERRAL_CODE,     
		$metadata,
                $redirectAutomatically,
                $walletMessage
            );
	
            $payurl = $invoice->getData()['checkoutLink'];
            
            if (!empty($payurl)){				
                $comment = 'Invoice URL: '.$payurl;
                $this->save_log_record('info', 'Invoice is loaded', 'Invoice URL: '.$payurl);
                $invoice_id = $invoice->getData()['id'];
                $_SESSION['transactionID'] = $invoice_id;			
                return $payurl;
            }
            else {
                $errorMessage = $this->languageTextManager->get_text('invoice request error', 'coinsnap');
                $this->save_log_record('error', 'Invoice request error', $errorMessage);
                $this->save_message_record('error', $errorMessage);
                exit;
            }
        }
        else {

            if ($checkInvoice['error'] === 'currencyError') {
                $errorMessage = strtoupper($currency).' - currency is not supported by Coinsnap';
            } elseif ($checkInvoice['error'] === 'amountError') {
                $errorMessage = 'Invoice amount cannot be less than '.$checkInvoice['min_value'].' '.strtoupper($currency);
            } else {
                $errorMessage = $checkInvoice['error'];
            }
            $this->save_log_record('error', 'Invoice request error', $errorMessage);
            $this->save_message_record('error', $errorMessage);
            exit;
        }
    }
    
    public function save_message_record($mode,$message){
        $languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
        $GLOBALS['messageStack']->add('checkout_payment', $message, $mode);
    }
    
    //  string $mode: error | info | debug | warning
    public function save_log_record($mode,$title,$message){
        //$logger = \Gambio\Core\Application::getInstance()->getLogger();
        //$logger->$mode($title, $message);
    }

    //  The save_order method stores the order and sets the orderId
    public function save_order(){
	$settings = new Settings();
        $orderId = $this->createOrder();

	$this->_setOrderId($orderId);
        $_SESSION['invoiceURL'] = $this->getInvoiceUrl($orderId);
        $_SESSION['orderTotal'] = $this->coo_order_total->output_array();

        $transactionModel = new CoinsnapTransactionModel();
        $transactionModel->create($settings, $_SESSION['transactionID'], $orderId, (array)$this->coo_order);        
        
    }

    //  Order creation
    private function createOrder(): string{
        return $this->orderWriteService->createNewCustomerOrder(
            $this->_getCustomerId(),
            $this->_getCustomerStatusInformation(),
            $this->_getCustomerNumber(),
            $this->_getCustomerEmail(),
            $this->_getCustomerTelephone(),
            $this->_getCustomerVatId(),
            $this->_getCustomerDefaultAddress(),
            $this->_getBillingAddress(),
            $this->_getDeliveryAddress(),
            $this->_getOrderItemCollection(),
            $this->_getOrderTotalCollection(),
            $this->_getOrderShippingType(),
            $this->_getOrderPaymentType(),
            $this->_getCurrencyCode(),
            $this->_getLanguageCode(),
            $this->_getOrderTotalWeight(),
            $this->_getComment(),
            $this->_getOrderStatusId(),
            $this->_getOrderAddonValuesCollection());
    }
    
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
}
