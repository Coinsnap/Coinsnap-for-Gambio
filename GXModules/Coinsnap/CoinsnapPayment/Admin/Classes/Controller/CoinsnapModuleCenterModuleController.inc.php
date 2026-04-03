<?php 
declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/../../loader.php')) {
    require_once dirname(__DIR__) . '/../../loader.php';
}

use Coinsnap\Client\Invoice;
use Coinsnap\Client\Store;
use Coinsnap\Client\Webhook;

if(!defined('COINSNAP_GAMBIO_VERSION')){ define( 'COINSNAP_GAMBIO_VERSION', '1.1.0' ); }
if(!defined('COINSNAP_SERVER_URL')){ define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' ); }
if(!defined('COINSNAP_CURRENCIES')){ define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") ); }
if(!defined('COINSNAP_API_PATH')){ define( 'COINSNAP_API_PATH', '/api/v1/' ); }
if(!defined('COINSNAP_SERVER_PATH')){ define( 'COINSNAP_SERVER_PATH', 'stores' ); }
if(!defined('COINSNAP_REFERRAL_CODE')){ define( 'COINSNAP_REFERRAL_CODE', 'D17689' ); }
if(!defined('COINSNAP_ICON_URL')){ define( 'COINSNAP_ICON_URL', '/images/icons/payment/coinsnap_bitcoin_lightning.png' ); }

class CoinsnapModuleCenterModuleController extends AbstractModuleCenterModuleController {
	
    protected $configuration;
    private $localeLanguageMapping = [
	'de-DE' => 'german',
	'fr-FR' => 'french',
	'it-IT' => 'italian',
	'en-US' => 'english',
    ];
    
    public const COINSNAP_WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];
    public const BTCPAY_WEBHOOK_EVENTS = ['InvoiceCreated','InvoiceExpired','InvoiceSettled','InvoiceProcessing'];

    protected function _init(): void {
        $this->pageTitle = 'Coinsnap ' . $this->languageTextManager->get_text('payment', 'coinsnap');
        $this->configuration = MainFactory::create('CoinsnapStorage');
    }

    /**
     * @return AdminLayoutHttpControllerResponse
     * @throws Exception
     */
    public function actionDefault(): AdminLayoutHttpControllerResponse {
        $title = new NonEmptyStringType('Coinsnap ' . $this->languageTextManager->get_text('payment', 'coinsnap'));
	$template = $this->getTemplateFile('Coinsnap/CoinsnapPayment/Admin/Templates/coinsnap_configuration.html');

	$data = MainFactory::create('KeyValueCollection',
            ['pageToken' => $_SESSION['coo_page_token']->generate_token(),
            'configuration' => $this->configuration->get_all(),
            'translate_section' => 'coinsnap',
            'action_save_configuration' => xtc_href_link('admin.php', 'do=CoinsnapModuleCenterModule/SaveConfiguration'),
	]);

	return MainFactory::create('AdminLayoutHttpControllerResponse', $title, $template, $data);
    }

    /**
    * @return RedirectHttpControllerResponse
    * @throws Exception
    */
    public function actionSaveConfiguration(): RedirectHttpControllerResponse {
        $this->_validatePageToken();

        $newConfiguration = $this->_getPostData('configuration');
        $oldConfiguration = $this->configuration->get_all();
        
        //  We save new configuration 
        foreach ($newConfiguration as $key => $value) {
            try {
                $this->configuration->set($key, $value);
            }
            catch (Exception $e) {
                $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_saving_configuration', 'coinsnap'), 'error');
            }
        }
        
        if ($newConfiguration) {
            
            $provider = ($newConfiguration['provider'] === 'btcpay')? 'btcpay' : 'coinsnap';
            $apiUrl = ($provider === 'btcpay')? $newConfiguration['btcpay_serverurl'] : COINSNAP_SERVER_URL;
            $storeId = ($provider === 'btcpay')? $newConfiguration['btcpay_storeid'] : $newConfiguration['storeid'];
            $apiKey = ($provider === 'btcpay')? $newConfiguration['btcpay_apikey'] : $newConfiguration['apikey'];
            
            $client = new Invoice($apiUrl, $apiKey);
            $store = new Store($apiUrl, $apiKey);
            
            try {
                $this_store = $store->getStore($storeId);
            }
            catch (Exception $e) {
                $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_saving_configuration', 'coinsnap'), 'error');
		$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_sync_payment_methods_please_check_credentials', 'coinsnap'), 'error');
		return MainFactory::create('RedirectHttpControllerResponse',xtc_href_link('admin.php', 'do=CoinsnapModuleCenterModule'));
            }
            
            if ($this_store) {
                
                if (! $this->webhookExists($apiUrl, $apiKey, $storeId)){
                    if (! $this->registerWebhook($apiUrl, $apiKey, $storeId, $provider)) {
                        $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_error','coinsnap'),'error');                           			}
                    else {
                        $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_registered', 'coinsnap'), 'info');
                    }
                }
                else {
                    $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_exists', 'coinsnap'), 'info');
                }
                
                $descriptions = [];
                $languageMapping = $this->localeLanguageMapping;
                foreach ($languageMapping as $locale => $language) {
                    $descriptions[$language] = $translations[$language]['description'] = 'Bitcoin + Lightning Payment';
                    $titles[$language] = $translations[$language]['title'] = 'Bitcoin + Lightning';
                }

                $data[] = [
                    'state' => 'True',
                    'logo_url' => COINSNAP_ICON_URL,
                    'logo_alt' => 'Bitcoin + Lightning',
                    'id' => 'coinsnap',
                    'module' => $translations['english']['title'],
                    'description' => $translations['english']['description'],
                    'fields' => [],
                    'titles' => $titles,
                    'descriptions' => $descriptions
                ];

                $key = 'MODULE_PAYMENT_COINSNAP';
                $query = xtc_db_query("SELECT * FROM `gx_configurations` WHERE `key` = '" . xtc_db_input('configuration/' . $key) . "'");
                $result = xtc_db_fetch_array($query);

                if (empty($result)) {
                    xtc_db_query("INSERT INTO `gx_configurations` (`key`, `value`, `sort_order`, `type`, `last_modified`) "
                        . "VALUES ('configuration/" . $key . "', 'false', '0', 'switcher', now())");

                    define($key . '_TITLE', $name . ' ' . $titles['english']);
                    define($key . '_DESC', $this->languageTextManager->get_text('would_you_like_to_enable_this_payment_method', 'coinsnap'));

                }
                else {
                    if ($result['value'] === 'true' && strtolower($paymentMethodStateOnPortal) === 'active') {
                        xtc_db_perform(
                            'gx_configurations',
                            ['value' => 'false'],
                            'update',
                            'key = ' . xtc_db_input('configuration/' . $key)
                        );
                    }
                }
                $this->configuration->set('payment_methods', \json_encode($data));
                
                $currency = (defined(DEFAULT_CURRENCY) && DEFAULT_CURRENCY !== null)? DEFAULT_CURRENCY : 'EUR';

                $connectionData = '';

                if ($provider === 'btcpay') {

                        try {
                            $storePaymentMethods = $store->getStorePaymentMethods($storeId);

                            if ($storePaymentMethods['code'] === 200) {
                                if ($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                                    $checkInvoice = $client->checkPaymentData(0, $currency, 'bitcoin', 'calculation');
                                } elseif ($storePaymentMethods['result']['lightning']) {
                                    $checkInvoice = $client->checkPaymentData(0, $currency, 'lightning', 'calculation');
                                }
                            }
                        } catch (\Exception $e) {
                            $errorMessage = $this->languageTextManager->get_text('error_connection', 'coinsnap');
                        }

                }
                else {
                        $checkInvoice = $client->checkPaymentData(0, $currency, 'coinsnap', 'calculation');
                }

                if (isset($checkInvoice) && $checkInvoice['result']){
                        $connectionData = $this->languageTextManager->get_text('text_order_amount', 'coinsnap') .' '. $checkInvoice['min_value'].' '.$currency;
                } else {
                        $connectionData = $this->languageTextManager->get_text('error_no_payment_method', 'coinsnap');
                }
            }
            else {
                $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_saving_configuration', 'coinsnap'). ' '. $connectionData, 'error');
		$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_sync_payment_methods_please_check_credentials', 'coinsnap'), 'error');
		return MainFactory::create('RedirectHttpControllerResponse',xtc_href_link('admin.php', 'do=CoinsnapModuleCenterModule'));
            }
        }

	$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('configuration_saved', 'coinsnap'). ' '. $connectionData, 'info');

        return MainFactory::create( 'RedirectHttpControllerResponse', xtc_href_link('admin.php', 'do=CoinsnapModuleCenterModule'));
    }
    
    public function getWebhookUrl(){
        return $webhook_url = xtc_catalog_href_link("shop.php", 'do=CoinsnapWebhook/Index');
    }
    
    public function webhookExists(string $apiUrl, string $apiKey, string $storeId): bool{
        
        $whClient = new \Coinsnap\Client\Webhook($apiUrl, $apiKey);
        if($this->configuration->get('webhook')){
            
            $storedWebhook = json_decode($this->configuration->get('webhook'),true);
                
            if ($storedWebhook && is_array($storedWebhook)) {

                try {
                    $existingWebhook = $whClient->getWebhook($storeId, $storedWebhook['id']);

                    if ($existingWebhook->getData()['id'] === $storedWebhook['id'] && strpos($existingWebhook->getData()['url'], $storedWebhook['url']) !== false) {
                        return true;
                    }
                } catch (\Throwable $e){
                    $errorMessage = 'Error fetching existing Webhook (' .$e->getMessage().')';
                    return false;
                }
            }
            try {
                $storeWebhooks = $whClient->getWebhooks($storeId);
                foreach ($storeWebhooks as $webhook) {
                    if (strpos($webhook->getData()['url'], $this->getWebhookUrl()) !== false) {
                        $whClient->deleteWebhook($storeId, $webhook->getData()['id']);
                    }
                }
            } catch (\Throwable $e) {
                $errorMessage = 'Error fetching webhooks for store ID '.$storeId.'. (' .$e->getMessage().')';
                return false;
            }
        }
        return false;
    }
	
    public function registerWebhook(string $apiUrl, string $apiKey, string $storeId, string $provider){
        try {
            $whClient = new Webhook($apiUrl, $apiKey);
            $webhook_events = ($provider === 'btcpay') ? self::BTCPAY_WEBHOOK_EVENTS : self::COINSNAP_WEBHOOK_EVENTS;
            $webhook = $whClient->createWebhook(
                $storeId,   //$storeId
                $this -> getWebhookUrl(), //$url
                $webhook_events,   //$specificEvents
                null    //$secret
            );
            
            $webhook_data = [
                    'id' => $webhook->getData()['id'],
                    'secret' => $webhook->getData()['secret'],
                    'url' => $webhook->getData()['url']
            ];
            
            
            $this->configuration->set('webhook', json_encode($webhook_data));
            
            return $webhook;

        } catch (\Throwable $e) {
            $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_error','coinsnap') . ' ('.$e->getMessage().')','error');
            return false;
        }
    }
}
