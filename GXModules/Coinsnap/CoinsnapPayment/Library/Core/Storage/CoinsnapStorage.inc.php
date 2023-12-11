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

    public function set($p_key, $p_value){
        if(!empty($p_key) && !empty($p_value)){
            $value = strip_tags($p_value);
            $rc = parent::set($p_key, $value);
            return $rc;
        }
    }
}
