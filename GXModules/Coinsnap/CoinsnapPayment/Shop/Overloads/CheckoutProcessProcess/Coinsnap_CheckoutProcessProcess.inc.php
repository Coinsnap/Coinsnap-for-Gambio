<?php

use GXModules\Library\{
    Core\Settings\Struct\Settings,
    Helper\CoinsnapHelper
};

use GXModules\Shop\Classes\Model\CoinsnapTransactionModel;
use Coinsnap\Util\PreciseNumber;

//use Coinsnap\Model\{AddressCreate, LineItemCreate, LineItemType, Transaction, TransactionCreate};
//use Coinsnap\Model\TransactionPending;

class Coinsnap_CheckoutProcessProcess extends Coinsnap_CheckoutProcessProcess_parent{

//  The proceed method is the main method of the class and performs the complete checkout process.
    public function proceed(){//:bool
        
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
        
        if(isset($_SESSION['invoiceURL'])) $this->set_redirect_url($_SESSION['invoiceURL']);
                
        return true;
    }
        
    public function getInvoiceUrl($order_id){        
		
        $order = (array)$this->coo_order;
        
	if ($configuration === null) {
            $configuration = \MainFactory::create('CoinsnapStorage');
        }
        
        $amount = number_format($order['info']['total'],2);
        $currency_code = $order['info']['currency'];
	$redirectUrl = xtc_href_link("checkout_success.php");
		
	$buyerName = $order['customer']['firstname'].' '.$order['customer']['lastname'];
	$buyerEmail = $order['customer']['email_address'];

	$metadata = [];
	$metadata['orderNumber'] = $order_id;
	$metadata['customerName'] = $buyerName;
		
	$checkoutOptions = new \Coinsnap\Client\InvoiceCheckoutOptions();
		
	$checkoutOptions->setRedirectURL( $redirectUrl );
	$client = new \Coinsnap\Client\Invoice( COINSNAP_SERVER_URL, $configuration->get('apikey'));			
	$amount = Coinsnap\Util\PreciseNumber::parseFloat($amount,2);
	$invoice = $client->createInvoice(
            $configuration->get('storeid'),  
            $currency_code,
            $amount,
            $order_id,
            $buyerEmail,
            $buyerName, 
            $redirectUrl,
            '',     
            $metadata,
            $checkoutOptions
	);
        
        $payurl = $invoice->getData()['checkoutLink'];
        
        if (!empty($payurl)){				
            $invoice_id = $invoice->getData()['id'];
            $_SESSION['transactionID'] = $invoice_id;			
            return  $payurl;
	}
	else {
            exit;
	}		
    }

    //  The save_order method stores the order and sets the orderId
    public function save_order(){
	$settings = new Settings();
        $orderId = $this->createOrder();
        $createdTransactionId = '';

	$this->_setOrderId($orderId);
        $_SESSION['invoiceURL'] = $this->getInvoiceUrl($orderId);
        $_SESSION['orderTotal'] = $this->coo_order_total->output_array();

        $transactionModel = new CoinsnapTransactionModel();
        
	/**
	* @param Settings $settings
	* @param string $transactionId
	* @param string $orderId
	* @param array $orderData
        */
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
}
