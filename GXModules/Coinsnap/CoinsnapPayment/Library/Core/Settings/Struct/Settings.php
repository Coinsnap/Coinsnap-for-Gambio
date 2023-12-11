<?php 
declare(strict_types=1);

namespace GXModules\Library\Core\Settings\Struct;

use Coinsnap\ApiClient;

class Settings {

    public const SHOP_SYSTEM = 'x-meta-shop-system';
    public const SHOP_SYSTEM_VERSION = 'x-meta-shop-system-version';
    public const SHOP_SYSTEM_AND_VERSION = 'x-meta-shop-system-and-version';

    protected $apiClient;   //  Coinsnap\ApiClient
    protected $storeid;     //  Store ID  @var string
    protected $apikey;      //  API Key @var string

    public function __construct($configuration = null) {    //  @param $configuration
        if ($configuration === null) {
            $configuration = \MainFactory::create('CoinsnapStorage');
        }
        
        $this->setStoreID($configuration->get('storeid'));
        $this->setAPIKey($configuration->get('apikey'));
        $this->setActive((bool) $configuration->get('active'));
    }

    public function getStoreID(): string{
        return strval($this->storeid);
    }

    protected function setStoreID($storeid): void{
        $this->storeid = $storeid;
    }

    public function getAPIKey(): string{
        return strval($this->apikey);
    }

    protected function setAPIKey($apikey): void{
        $this->apikey = $apikey;
    }

    public function isActive(): bool {
	return boolval($this->active);
    }

    protected function setActive(bool $active): void {
	$this->active = $active;
    }

    public function getApiClient(): APIClient {
        if (is_null($this->apiClient)) {
            $this->apiClient = new ApiClient($this->getStoreID(), $this->getAPIKey());
	}
        return $this->apiClient;
    }

    protected static function getDefaultHeaderData() {
        $gx_version = include DIR_FS_CATALOG . 'release_info.php';
	
        $shop_version = str_replace('v', '', $gx_version);
        [$major_version, $minor_version, $_] = explode('.', $shop_version, 3);
        return [
            self::SHOP_SYSTEM             => 'gambio',
            self::SHOP_SYSTEM_VERSION     => $shop_version,
            self::SHOP_SYSTEM_AND_VERSION => 'gambio-' . $major_version . '.' . $minor_version,
	];
    }
}