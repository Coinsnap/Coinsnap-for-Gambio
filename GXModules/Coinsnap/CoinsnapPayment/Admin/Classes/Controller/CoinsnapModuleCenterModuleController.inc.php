<?php declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/../../loader.php')) {
    require_once dirname(__DIR__) . '/../../loader.php';
}

use Coinsnap\Client\ApiKey;
use Coinsnap\Client\Invoice;
use Coinsnap\Client\Server;
use Coinsnap\Client\Store;
use Coinsnap\Client\StorePaymentMethod;
use Coinsnap\Client\APIWebhook;
use Coinsnap\Client\Webhook;
use Coinsnap\Client\WebhookCreated;
use Coinsnap\Result\AbstractStorePaymentMethodResult;
use Coinsnap\Result\ServerInfo;

define( 'COINSNAP_VERSION', '1.0' );
define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );
define( 'COINSNAP_API_PATH', '/api/v1/' );
define( 'COINSNAP_SERVER_PATH', 'stores' );
define( 'COINSNAP_REFERRAL_CODE', '' );

class CoinsnapModuleCenterModuleController extends AbstractModuleCenterModuleController {
	
    protected $configuration;
    private $localeLanguageMapping = [
	'de-DE' => 'german',
	'fr-FR' => 'french',
	'it-IT' => 'italian',
	'en-US' => 'english',
    ];

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
	$template = $this->getTemplateFile('Coinsnap/CoinsnapPayment/Admin/Html/coinsnap_configuration.html');

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
            $client = new Store(COINSNAP_SERVER_URL, $newConfiguration['apikey']);
            if (!empty($store = $client->getStore($newConfiguration['storeid']))) {
                //  Webhook checking
                $stored_webhook = array(
                    'id' => $this->configuration->get('webhook_id'),
                    'secret' => $this->configuration->get('webhook_secret'),
                    'url' => $this->configuration->get('webhook_url')
                );
                
                if (APIWebhook::webhookExists(COINSNAP_SERVER_URL, $newConfiguration['apikey'], $newConfiguration['storeid'], $stored_webhook)){
                    $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_exists', 'coinsnap'), 'info');
		}
                else {
                    //  If webhook exists and configuration has been changed
                    if(isset($oldConfiguration['webhook_id']) && $oldConfiguration['webhook_id'] > 0 && ($newConfiguration['apikey'] != $oldConfiguration['apikey'] || $newConfiguration['storeid'] != $oldConfiguration['storeid'])){
                        
                    }
                    else {
                        // Register a new webhook.
                        $webhookUrl = xtc_catalog_href_link("shop.php", 'do=CoinsnapWebhook/Index');
                        $webhook = APIWebhook::registerWebhook(COINSNAP_SERVER_URL, $newConfiguration['apikey'], $newConfiguration['storeid'], $webhookUrl);

                        foreach($webhook as $webhook_key => $webhook_value){
                            $this->configuration->set('webhook_'.$webhook_key, $webhook_value);
                        }

                        if ($webhook) {
                            $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_registered', 'coinsnap'), 'info');
                        }
                        else {
                            $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('coinsnap_webhook_error', 'coinsnap'), 'error');
                        }
                    }
                }
                
                $descriptions = [];
                $languageMapping = $this->localeLanguageMapping;
                foreach ($languageMapping as $locale => $language) {
                    $descriptions[$language] = $translations[$language]['description'] = 'Bitcoin + Lightning Payment';
                    $titles[$language] = $translations[$language]['title'] = 'Bitcoin + Lightning';
                }

                $data[] = [
                    'state' => 'True',
                    'logo_url' => '/images/icons/payment/coinsnap_bitcoin_lightning.png',
                    'logo_alt' => 'Bitcoin + Lightning',
                    'id' => 'coinsnap_bitcoin',
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
                    $install_query = "insert into `gx_configurations` (`key`, `value`, `sort_order`, `type`, `last_modified`) "
                        . "values ('configuration/" . $key . "', 'false', '0', 'switcher', now())";
                    xtc_db_query($install_query);

                    define($key . '_TITLE', $name . ' ' . $titles['english']);
                    define($key . '_DESC', $this->languageTextManager->get_text('would_you_like_to_enable_this_payment_method', 'coinsnap'));

                }
                else {
                    if ($result['state'] === 'true' && strtolower($paymentMethodStateOnPortal) === 'active') {
                        xtc_db_perform(
                            'gx_configurations',
                            ['value' => 'false'],
                            'update',
                            'key = ' . xtc_db_input('configuration/' . $key)
                        );
                    }
                }
                $this->configuration->set('payment_methods', \json_encode($data));
                
            }
            else {
                $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_saving_configuration', 'coinsnap'), 'error');
		$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_sync_payment_methods_please_check_credentials', 'coinsnap'), 'error');
		return MainFactory::create('RedirectHttpControllerResponse',xtc_href_link('admin.php', 'do=CoinsnapModuleCenterModule'));
            }
        }

	$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('configuration_saved', 'coinsnap'), 'info');

        return MainFactory::create( 'RedirectHttpControllerResponse', xtc_href_link('admin.php', 'do=CoinsnapModuleCenterModule'));
    }
}
