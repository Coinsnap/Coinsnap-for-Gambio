<?php 
declare(strict_types=1);
	
defined('GM_HTTP_SERVER') || define('GM_HTTP_SERVER', HTTP_SERVER);
	
if (file_exists(dirname(__DIR__) . '/../../../GXModules/Coinsnap/CoinsnapPayment/loader.php')) {
    require_once dirname(__DIR__) . '/../../../GXModules/Coinsnap/CoinsnapPayment/loader.php';
}

define( 'COINSNAP_VERSION', '1.0' );
define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );
define( 'COINSNAP_API_PATH', '/api/v1/' );
define( 'COINSNAP_SERVER_PATH', 'stores' );
define( 'COINSNAP_REFERRAL_CODE', 'D17689' );
	
class coinsnap {
		
    protected $languageTextManager; //var LanguageTextManager
		
    public function __construct(){
        global $order;
        $this->code = 'coinsnap';
        $this->languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
        $this->_initLanguageConstants();
			
        $this->title = 'Bitcoin + Lightning';
        $this->title = '<img src="/images/icons/payment/coinsnap_bitcoin_lightning.png" alt="'.$this->code.'">' . $this->title;
        
        $this->description = 'Coinsnap ' . $this->languageTextManager->get_text('description', 'coinsnap');
			
        $this->sort_order = defined('MODULE_PAYMENT_' . strtoupper($this->code) . '_SORT_ORDER') ? constant('MODULE_PAYMENT_' . strtoupper($this->code). '_SORT_ORDER') : 0;
        $this->enabled = defined('MODULE_PAYMENT_' . strtoupper($this->code) . '_STATUS') && filter_var(constant('MODULE_PAYMENT_' . strtoupper($this->code) . '_STATUS'), FILTER_VALIDATE_BOOLEAN);
        $this->info = defined('MODULE_PAYMENT_' . strtoupper($this->code) . '_TEXT_INFO') ? constant('MODULE_PAYMENT_' . strtoupper($this->code) . '_TEXT_INFO') : '';
        
        if (defined('MODULE_PAYMENT_' . strtoupper($this->code) . '_ORDER_STATUS_ID') && constant('MODULE_PAYMENT_' . strtoupper($this->code) . '_ORDER_STATUS_ID') > 0) {
            $this->order_status = constant('MODULE_PAYMENT_' . strtoupper($this->code) . '_ORDER_STATUS_ID');
        }
			
        $this->tmpStatus = defined('MODULE_PAYMENT_' . strtoupper($this->code) . '_TMPORDER_STATUS_ID') ? (int)constant('MODULE_PAYMENT_' . strtoupper($this->code) . '_TMPORDER_STATUS_ID') : 0;
        
        if (is_object($order)) {
            $this->update_status();
	}
    }
		
    public function update_status(){}

    public function javascript_validation(){
        return false;
    }
		
    public function selection(){
        $selection = array('id' => $this->code,'module' => $this->title,'description' => $this->description,'fields' => array());
	return $selection;
    }
		
    public function pre_confirmation_check(){
	return false;
    }
		
    public function confirmation(){
        $confirmation = ['title' => $this->title];
	return $confirmation;
    }
		
    public function refresh(){}
		
    public function process_button(){
        return '';
    }
		
    public function payment_action(){
        $redirectUrl = xtc_href_link('shop.php', 'do=CoinsnapPayment/PaymentPage?payment_error=' . $this->code, 'SSL');
        xtc_redirect($redirectUrl, '');
    }
		
    public function before_process(){
        return false;
    }
		
    public function after_process(){}
		
    public function get_error(){
        if (isset($_SESSION['coinsnap_error'])) {
            $error = array('error' => $_SESSION['coinsnap_error']);
            unset($_SESSION['coinsnap_error']);
            return $error;
	}
	return false;
    }
		
    public function check(){
        if (!isset ($this->_check)) {
            $check_query = xtc_db_query("select `value` from " . TABLE_CONFIGURATION . " where `key` = 'configuration/MODULE_PAYMENT_" . strtoupper($this->code) . "_STATUS'");
            $this->_check = xtc_db_num_rows($check_query);
	}
	return $this->_check;
    }
		
    public function install(){
	$config = $this->_configuration();
        $sort_order = 0;
	foreach ($config as $key => $data) {
            
            echo "Install: configuration/MODULE_PAYMENT_" . strtoupper($this->code) . "_" . $key;
            
            $install_query = "insert into `gx_configurations` (`key`, `value`, `sort_order`, `type`, `last_modified`) values ('configuration/MODULE_PAYMENT_" . 
            strtoupper($this->code) . "_" . $key . "', '". $data['value'] . "', '" . $sort_order . "', '" . addslashes($data['type'] ?? ''). "', now())";
            xtc_db_query($install_query);
            $sort_order++;
	}
	
        $defaultOrderStatus = [
            'NEW_STATUS_ID' => ['names' => ['en' => 'New', 'de' => 'Neu'],'color' => '68BBE3'],
            'EXPIRED_STATUS_ID' => ['names' => ['en' => 'Expired', 'de' => 'Abgelaufen'],'color' => 'e0412c'],
            'PROCESSING_STATUS_ID' => ['names' => ['en' => 'Processing', 'de' => 'Verarbeitung'],'color' => '68BBE3'],
            'SETTLED_STATUS_ID' => ['names' => ['en' => 'Settled', 'de' => 'Erledigt'],'color' => '45a845']
	];
        
        foreach ($defaultOrderStatus as $configKey => $orderStatusDefaults) {
            $this->updateConfiguration($configKey,$this->getOrdersStatus($orderStatusDefaults['names'],$orderStatusDefaults['color']));
        }
    }
		
    public function _configuration(){
        $config = ['STATUS' => ['value' => 'True', 'type' => 'switcher'],'SORT_ORDER' => ['value' => '0']];
        /*  Creating checkbox for each payment method - for the future. Now we have Bitcoin+Lightning only
	foreach ($this->getPaymentMethods() as $method) {
            $title = (!empty($method['titles'][$_SESSION['language']]))? $method['titles'][$_SESSION['language']] : $title = $method['titles']['english'];
            define('MODULE_PAYMENT_COINSNAP_' . strtoupper($method['id']) . '_TITLE', $title);
            define('MODULE_PAYMENT_COINSNAP_' . strtoupper($method['id']) . '_DESC', $this->languageTextManager->get_text('would_you_like_to_enable_this_payment_method', 'coinsnap'));
            $config[strtoupper($method['id'])] = ['value' => 'True', 'type' => 'switcher'];
	}*/
	return $config;
    }
		
    protected function updateConfiguration($configurationKey, $configurationValue){
	$db = StaticGXCoreLoader::getDatabaseQueryBuilder();
	$db->where('key', 'configuration/MODULE_PAYMENT_' . strtoupper($this->code) . '_' . $configurationKey);
	$db->update(TABLE_CONFIGURATION, ['value' => $configurationValue]);
    }
		
    protected function getOrdersStatus($names, $color){
        
        $orderStatusId = null;
	$orderStatusService = StaticGXCoreLoader::getService('OrderStatus');
	
        /** @var \OrderStatusInterface $orderStatus */
	foreach ($orderStatusService->findAll() as $orderStatus) {
            foreach ($names as $languageCode => $statusName) {
                if($orderStatus->getName(MainFactory::create('LanguageCode', new StringType($languageCode))) === $statusName){
                    $orderStatusId = $orderStatus->getId();
                    break 2;
		}
            }
	}
	
        if ($orderStatusId === null) {
            $newOrderStatus = MainFactory::create('OrderStatus');
            foreach ($names as $languageCode => $statusName) {
                $newOrderStatus->setName(MainFactory::create('LanguageCode', new StringType($languageCode)), new StringType($statusName));
            }
            $newOrderStatus->setColor(new StringType($color));
            $orderStatusId = $orderStatusService->create($newOrderStatus);
	}
	return $orderStatusId;
    }
		
    public function remove(){
        xtc_db_query("delete from " . TABLE_CONFIGURATION . " where `key` in ('" . implode("', '", $this->keys()) . "')");
    }
		
    public function keys(){ //  Determines the module's configuration keys, return array
        $ckeys = array_keys($this->_configuration());
        $keys = array();
        foreach ($ckeys as $k) {
            $keys[] = 'configuration/MODULE_PAYMENT_' . strtoupper($this->code) . '_' . $k;
	}
	return $keys;
    }
		
    public function isInstalled(){
        foreach ($this->keys() as $key) {
            if (!defined($key)) {
		return false;
            }
	}
	return true;
    }
		
    protected function getPaymentMethods(){
        $configuration = \MainFactory::create('CoinsnapStorage');
        $coinsnap_storage_array = $configuration->get_all();
        $paymentMethods = json_decode($configuration->get('payment_methods'), true);
	return $paymentMethods;
    }
		
    protected function _initLanguageConstants(){
        $prefix = 'MODULE_PAYMENT_%s';
			
	$constantNames = [
            sprintf($prefix . '_STATUS_TITLE', strtoupper($this->code)),
            sprintf($prefix . '_STATUS_DESC', strtoupper($this->code)),
            sprintf($prefix . '_SORT_ORDER_TITLE', strtoupper($this->code)),
            sprintf($prefix . '_SORT_ORDER_DESC', strtoupper($this->code)),
            sprintf($prefix . '_SORT_ORDER_ASC', strtoupper($this->code)),
	];
			
        foreach ($constantNames as $constantName) {
            $translationKey = 'configuration' . strtolower(str_replace(sprintf($prefix, strtoupper($this->code)), '', $constantName));
            defined($constantName) or define($constantName, $this->languageTextManager->get_text($translationKey, 'coinsnap'));
        }
    }
}
	
MainFactory::load_origin_class('coinsnap');