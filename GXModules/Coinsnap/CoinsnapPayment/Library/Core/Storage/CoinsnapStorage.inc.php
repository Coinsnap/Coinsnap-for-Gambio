<?php declare(strict_types = 1);

class CoinsnapStorage extends ConfigurationStorage {

    const CONFIG_VERSION = 'version';
    const CONFIG_STOREID = 'storeid';
    const CONFIG_APIKEY = 'apikey';

    //  namespace inside the configuration storage
    const CONFIG_STORAGE_NAMESPACE = 'modules/Coinsnap/CoinsnapPayment';

    //  array holding default values to be used in absence of configured values
    protected $default_configuration;

    //  constructor; initializes default configuration
    public function __construct(){
        parent::__construct(self::CONFIG_STORAGE_NAMESPACE);
        $this->setDefaultConfiguration();
    }

    //  fills $default_configuration with initial values
    protected function setDefaultConfiguration(){
        $this->default_configuration = [
            self::CONFIG_VERSION => 0,
            self::CONFIG_STOREID => '',
            self::CONFIG_APIKEY => ''
        ];
    }

    //  returns a single configuration value by its key
    public function get($key){  //   @param string $key a configuration key (relative to the namespace prefix)
        $value = parent::get($key);
	if ($value === false && array_key_exists($key, $this->default_configuration)) {
            $value = $this->default_configuration[$key];
	}
        return $value;  //  @return string configuration value
    }

    //  Retrieves all keys/values from a given prefix namespace
    public function get_all($p_prefix = ''){    //  @param string $p_prefix
        $values = parent::get_all($p_prefix);
        foreach ($this->default_configuration as $key => $default_value) {
            $key_prefix = substr($key, 0, strlen($p_prefix));
		if (!array_key_exists($key, $values) && $key_prefix === $p_prefix) {
                    $values[$key] = $default_value;
		}
            }
        return $values; //  @return array
    }

    // Empty strings and '0' are stored too: an unticked checkbox must turn its feature off, and a cleared key must stay cleared.
    public function set($p_key, $p_value){
        if(!empty($p_key) && $p_value !== null){
            $value = strip_tags((string) $p_value);
            $rc = parent::set($p_key, $value);
            return $rc;
        }
    }

    // One webhook record per provider and store, so switching stores or providers never reuses or overwrites another store's secret.
    public static function webhookKey(string $provider, string $storeId): string {
        return 'webhook_' . ($provider === 'btcpay' ? 'btcpay' : 'coinsnap') . '_' . substr(hash('sha256', $storeId), 0, 16);
    }

    // The stored webhook (id, secret, url) of this provider and store, or null.
    // $legacy: fall back to the single "webhook" record of versions before 1.2.0 until the merchant saves the settings again.
    public function getWebhook(string $provider, string $storeId, bool $legacy = false): ?array {
        $webhook = json_decode((string) $this->get(self::webhookKey($provider, $storeId)), true);
        if (!is_array($webhook) && $legacy) {
            $webhook = json_decode((string) $this->get('webhook'), true);
        }
        return is_array($webhook) ? $webhook : null;
    }

    public function setWebhook(string $provider, string $storeId, array $webhook): void {
        $this->set(self::webhookKey($provider, $storeId), json_encode($webhook));
        // The old shared record is replaced by the per-store one.
        $this->set('webhook', '');
    }
}
