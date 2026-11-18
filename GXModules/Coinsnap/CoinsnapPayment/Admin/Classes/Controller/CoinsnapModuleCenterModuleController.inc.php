<?php 
declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/../../loader.php')) {
    require_once dirname(__DIR__) . '/../../loader.php';
}

use Coinsnap\Client\Invoice;
use Coinsnap\Client\Store;
use Coinsnap\Client\Webhook;
use GXModules\Library\Btcpay\KeyPermissions;

if(!defined('COINSNAP_GAMBIO_VERSION')){ define( 'COINSNAP_GAMBIO_VERSION', '1.2.0' ); }
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

    // The only settings the form may write. Anything else posted (for example a webhook record) is ignored.
    private const FORM_FIELDS = ['provider','storeid','apikey','btcpay_serverurl','btcpay_storeid','btcpay_apikey','autoredirect','returnurl',
        'discount_enabled','discount_type','discount_amount','discount_amount_limit','discount_percentage'];
    private const CHECKBOXES = ['autoredirect','discount_enabled'];

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
            'configuration' => $this->configurationForView(),
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

        $newConfiguration = $this->formSettings((array) $this->_getPostData('configuration'));
        $newConfiguration = $this->keepStoredApiKeys($this->refuseUnrestrictedBtcpayKey($newConfiguration));

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
                
                try {
                    $webhookExists = $this->webhookExists($apiUrl, $apiKey, $storeId, $provider);
                } catch (\Throwable $e) {
                    // An API error is not "no webhook": registering now would replace a working secret.
                    $webhookExists = true;
                    $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_error','coinsnap'),'error');
                }
                if (! $webhookExists){
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
    
    // True only when this store's own webhook record (secret included) matches a webhook on the server.
    public function webhookExists(string $apiUrl, string $apiKey, string $storeId, string $provider): bool{
        
        $whClient = new \Coinsnap\Client\Webhook($apiUrl, $apiKey);
        $storedWebhook = $this->configuration->getWebhook($provider, $storeId);
            
        if ($storedWebhook !== null && !empty($storedWebhook['id']) && !empty($storedWebhook['secret'])) {

            // Throws when the API can't be asked; the caller must not treat that as "no webhook".
            try {
                $existingWebhook = $whClient->getWebhook($storeId, (string) $storedWebhook['id']);
                if ($existingWebhook->getData()['id'] === $storedWebhook['id'] && strpos($existingWebhook->getData()['url'], $storedWebhook['url']) !== false) {
                    return true;
                }
            } catch (\Coinsnap\Exception\RequestException $e) {
                if (!in_array($e->getCode(), [400, 404], true)) {
                    throw $e;
                }
            }
        }
        // No usable record (first save, or a record from before 1.2.0): remove this shop's old webhooks, so their secret stops working.
        foreach ($whClient->getWebhooks($storeId) as $webhook) {
            if (strpos($webhook->getData()['url'], $this->getWebhookUrl()) !== false) {
                try {
                    $whClient->deleteWebhook($storeId, $webhook->getData()['id']);
                } catch (\Throwable $e) {
                    // A stale webhook that can't be removed only causes rejected deliveries.
                }
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
            
            
            $this->configuration->setWebhook($provider, $storeId, $webhook_data);
            
            return $webhook;

        } catch (\Throwable $e) {
            $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_error','coinsnap'),'error');
            return false;
        }
    }

    // The page gets no webhook secret and only a hint of each API key.
    private function configurationForView(): array
    {
        $configuration = $this->configuration->get_all();
        foreach (array_keys($configuration) as $key) {
            if (strpos((string) $key, 'webhook') === 0 || $key === 'seclog') {
                unset($configuration[$key]);
            }
        }
        foreach (['apikey', 'btcpay_apikey'] as $key) {
            $value = (string) ($configuration[$key] ?? '');
            $configuration[$key . '_hint'] = $value === '' ? '' : '••••••••' . substr($value, -4);
            $configuration[$key] = '';
        }
        return $configuration;
    }

    // Only the form's own fields; an unticked checkbox is saved as off.
    private function formSettings(array $posted): array
    {
        $settings = [];
        foreach (self::FORM_FIELDS as $field) {
            $settings[$field] = is_scalar($posted[$field] ?? null) ? trim((string) $posted[$field]) : '';
        }
        foreach (self::CHECKBOXES as $field) {
            $settings[$field] = $settings[$field] === '1' ? '1' : '0';
        }
        $settings['provider'] = $settings['provider'] === 'btcpay' ? 'btcpay' : 'coinsnap';
        if ($settings['btcpay_serverurl'] !== '' && !KeyPermissions::isServerUrl($settings['btcpay_serverurl'])) {
            $settings['btcpay_serverurl'] = '';
        }
        return $settings;
    }

    // A newly entered BTCPay key is saved only if BTCPay says it is limited to the configured store.
    // An unrestricted key controls the merchant's whole BTCPay account, payouts included.
    private function refuseUnrestrictedBtcpayKey(array $posted): array
    {
        $key = $posted['btcpay_apikey'];
        $urlUnchanged = $posted['btcpay_serverurl'] === (string) $this->configuration->get('btcpay_serverurl');
        $storedKey = $urlUnchanged ? (string) $this->configuration->get('btcpay_apikey') : '';
        $checkStored = $key === '' && $storedKey !== '' && $posted['provider'] === 'btcpay';
        if ($key === '' && !$checkStored) {
            return $posted;
        }
        $permissions = $this->btcpayKeyPermissions($posted['btcpay_serverurl'], $checkStored ? $storedKey : $key);
        if (KeyPermissions::acceptable($permissions ?? [], $posted['btcpay_storeid'])) {
            return $posted;
        }
        if ($checkStored) {
            // Keys saved before 1.2.0 keep working, but the merchant is asked to replace them.
            if ($permissions !== null) {
                $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_btcpay_key_unrestricted', 'coinsnap'), 'error');
            }
            return $posted;
        }
        $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_btcpay_key_refused', 'coinsnap'), 'error');
        $posted['btcpay_apikey'] = '';
        return $posted;
    }

    protected function btcpayKeyPermissions(string $serverUrl, string $apiKey): ?array
    {
        return KeyPermissions::fetch($serverUrl, $apiKey);
    }

    // Empty key fields keep the stored keys; a changed BTCPay URL never gets the stored BTCPay key.
    private function keepStoredApiKeys(array $posted): array
    {
        if (($posted['apikey'] ?? '') === '') {
            $posted['apikey'] = (string) $this->configuration->get('apikey');
        }
        $btcpayUrlUnchanged = (string) ($posted['btcpay_serverurl'] ?? '') === (string) $this->configuration->get('btcpay_serverurl');
        if (($posted['btcpay_apikey'] ?? '') === '' && $btcpayUrlUnchanged) {
            $posted['btcpay_apikey'] = (string) $this->configuration->get('btcpay_apikey');
        }
        return $posted;
    }
}
