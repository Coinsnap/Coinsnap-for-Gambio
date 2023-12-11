<?php declare(strict_types=1);

namespace GXModules\Library\Core\Service;

use ExistingDirectory;
use Gambio\Core\Cache\CacheFactory;
use GXModules\Library\{
    Core\Settings\Struct\Settings, 
    Helper\CoinsnapHelper
};
use LanguageTextManager;
use LegacyDependencyContainer;
use MainFactory;
use RequiredDirectory;
use StaticGXCoreLoader;
use Swaggest\JsonDiff\Exception;
use ThemeDirectoryRoot;
use ThemeId;
use ThemeService;
use ThemeSettings;
use CoinsnapStorage;

class PaymentService {
    
    protected $rootDir; //@var string
    public $settings; //@var Settings
    public $configuration; //@var CoinsnapStorage
    public $languageTextManager;// @var LanguageTextManager
    private $localeLanguageMapping = [//@var array
	'de-DE' => 'german',
	'fr-FR' => 'french',
	'it-IT' => 'italian',
	'en-US' => 'english',
    ];

    public function __construct(?CoinsnapStorage $configuration = null){
	$this->rootDir = __DIR__ . '/../../../../../../';
	$this->configuration = $configuration;
	$this->settings = new Settings($this->configuration);
	$this->languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
    }

    public function syncPaymentMethods(){
	
        $paymentMethods = $this->getPaymentMethodConfigurations();
        $translations = [];
	$data = [];
	/*
        /**
	 * PaymentMethodConfiguration $paymentMethod
	 */
        
	foreach ($paymentMethods as $paymentMethod) {
	    $name = 'Coinsnap ' . $paymentMethod->getName();
	    $slug = trim(strtolower(CoinsnapHelper::slugify($name)));

	    $descriptions = [];
	    $languageMapping = $this->localeLanguageMapping;
	    foreach ($paymentMethod->getResolvedDescription() as $locale => $text) {
		$language = $languageMapping[$locale];
		$descriptions[$language] = $translations[$language][$slug . '_description'] = addslashes($text);
	    }

	    $titles = [];
	    foreach ($paymentMethod->getResolvedTitle() as $locale => $text) {
		$language = $languageMapping[$locale];
		$titles[$language] = $translations[$language][$slug . '_title'] = addslashes(str_replace('-/', ' / ', $text));
	    }

	    $paymentMethodStateOnPortal = (string)$paymentMethod->getState();
	    $data[] = [
		'state' => $paymentMethodStateOnPortal,
		'logo_url' => $paymentMethod->getResolvedImageUrl(),
		'logo_alt' => $slug,
		'id' => $slug,
		'module' => $translations['english'][$slug . '_title'],
		'description' => $translations['english'][$slug . '_description'],
		'fields' => [],
		'titles' => $titles,
		'descriptions' => $descriptions
	    ];

	    $key = 'MODULE_PAYMENT_COINSNAP_' . strtoupper($slug);
	    $query = xtc_db_query("SELECT * FROM `gx_configurations` WHERE `key` = '" . xtc_db_input('configuration/' . $key) . "'");
	    $result = xtc_db_fetch_array($query);

	    if (empty($result)) {
		$install_query = "insert into `gx_configurations` (`key`, `value`, `sort_order`, `type`, `last_modified`) "
		    . "values ('configuration/" . $key . "', 'false', '0', 'switcher', now())";
		xtc_db_query($install_query);

		define($key . '_TITLE', $name . ' ' . $titles['english']);
		define($key . '_DESC', $this->languageTextManager->get_text('would_you_like_to_enable_this_payment_method', 'coinsnap'));
	    } else {
		if ($result['state'] === 'true' && strtolower($paymentMethodStateOnPortal) === 'active') {
		    xtc_db_perform(
			'gx_configurations',
			['value' => 'false'],
			'update',
			'key = ' . xtc_db_input('configuration/' . $key)
		    );
		}
	    }
	}

	$this->configuration->set('payment_methods', \json_encode($data));
        
    }

    /**
     * @return mixed|CoinsnapStorage
     */
    public function getConfiguration()
    {
	return $this->configuration ?? MainFactory::create('CoinsnapStorage');
    }

    /**
     * Fetch active merchant payment methods from Coinsnap API
     *
     * @return \Coinsnap\Sdk\Model\PaymentMethodConfiguration[]
     * @throws \Coinsnap\Sdk\ApiException
     * @throws \Coinsnap\Sdk\Http\ConnectionException
     * @throws \Coinsnap\Sdk\VersioningException
     */
    private function getPaymentMethodConfigurations(): array
    {
	
        $entityQueryFilter = (new EntityQueryFilter())
	    ->setOperator(CriteriaOperator::EQUALS)
	    ->setFieldName('state')
	    ->setType(EntityQueryFilterType::LEAF)
	    ->setValue(CreationEntityState::ACTIVE);

	$entityQuery = (new EntityQuery())->setFilter($entityQueryFilter);

	$settings = new Settings($this->configuration);
        
        print_r($settings);

        
        $apiClient = $settings->getApiClient();
        
        print_R($apiClient);
	$paymentMethodConfigurations = $apiClient->getPaymentMethodConfigurationService()->search($spaceId, $entityQuery);

	usort($paymentMethodConfigurations, function (PaymentMethodConfiguration $item1, PaymentMethodConfiguration $item2) {
	    return $item1->getSortOrder() <=> $item2->getSortOrder();
	});
        
        $paymentMethodConfigurations = array();

	return $paymentMethodConfigurations;
        
        
    }
}
