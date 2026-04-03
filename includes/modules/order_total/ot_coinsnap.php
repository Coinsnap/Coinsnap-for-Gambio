<?php


class ot_coinsnap {
    var $title, $output;
    
    public $code;
    public $description;
    public $enabled;
    public $sort_order;
    
    protected $languageTextManager;
    protected $configuration;

    public function __construct() {
        global $xtPrice;
        $this->code = 'ot_coinsnap';
        $this->languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
        $this->configuration = MainFactory::create('CoinsnapStorage');
        $this->title = $this->languageTextManager->get_text('coinsnap_discount_title', 'coinsnap');
        $this->description = $this->languageTextManager->get_text('coinsnap_discount_description', 'coinsnap');
        $this->enabled = 'true';
        $this->sort_order = '100';
        $this->output = array();
    }
    
    public function process(){
        global $order, $xtPrice;
        
        $isDiscount = false;
        $discount_enabled = (null !== $this->configuration->get('discount_enabled') && $this->configuration->get('discount_enabled') > 0)? true : false;
        
        if (!empty($order->info['payment_class']) && $order->info['payment_class'] === 'coinsnap' && $discount_enabled) {
            
            $discount_type = $this->configuration->get('discount_type');
            $discount_amount = (null !== $this->configuration->get('discount_amount'))? $this->configuration->get('discount_amount') : 0;
            $discount_percentage = (null !== $this->configuration->get('discount_percentage'))? floatval($this->configuration->get('discount_percentage')) : 0;
            
            $total = $order->info['total'];
            
            if($discount_type === 'fixed' && floatval($discount_amount) > 0){
                
                $discount_amount = round(floatval($discount_amount),2);
                $discount_amount_limit = (null !== $this->configuration->get('discount_amount_limit'))? floatval($this->configuration->get('discount_amount_limit')) : 0;
                
                if($discount_amount_limit >= 0 && $discount_amount_limit < 100){
                    if($discount_amount > ($total * $discount_amount_limit / 100)){
                        $discount_amount = round($total * $discount_amount_limit / 100,2);
                    }
                        
                    if($discount_amount < $total){
                        $isDiscount = true;
                        $discount_title = '';
                    }
                }
            }
            elseif($discount_type === 'percentage' && $discount_percentage > 0) {
                if($discount_percentage > 0 && $discount_percentage < 100){
                    $isDiscount = true;
                    $discount_title = ' '.$discount_percentage . '%';
                    $discount_amount = round($total * $discount_percentage / 100,2);
                }
            }
            
            if($isDiscount){
                
                $order->info['total'] -= $discount_amount;
                $order->info['bitcoin_discount'] = $discount_amount;
                
                $discount_text = $this->languageTextManager->get_text('coinsnap_discount_output', 'coinsnap') . $discount_title;
                
                $this->output[] = [
                    'title' => $discount_text,
                    'text'  => '-' . $xtPrice->xtcFormat($discount_amount, true),
                    'value' => -$discount_amount
                ];
            }
            
        }
    }
/*
    function process() {
      global $order, $xtPrice;

      $this->title = $_SESSION['customers_status']['customers_status_ot_discount'] . ' % ' . SUB_TITLE_OT_DISCOUNT;
      if ($_SESSION['customers_status']['customers_status_ot_discount_flag'] == '1' && $_SESSION['customers_status']['customers_status_ot_discount']!='0.00') {
		  // BOF GM_MOD:
        $discount_price = round($xtPrice->xtcFormat($order->info['subtotal'], false) / 100 * $_SESSION['customers_status']['customers_status_ot_discount']*-1, 2);
        $order->info['subtotal'] = $order->info['subtotal'] + $discount_price;
        $this->output[] = array('title' => $this->title . ':',
                                'text' => $xtPrice->xtcFormat($discount_price,true),
                                'value' => $discount_price);
      }
    }
*/
    function check() {
      if (!isset($this->_check)) {
        $check_query = xtc_db_query("select `value` from `gx_configurations` where `key` = 'configuration/MODULE_ORDER_TOTAL_COINSNAP_STATUS'");
        $this->_check = xtc_db_num_rows($check_query);
      }

      return $this->_check;
    }

    function keys() {
      return array('configuration/MODULE_ORDER_TOTAL_COINSNAP_STATUS', 'configuration/MODULE_ORDER_TOTAL_COINSNAP_SORT_ORDER');
    }

    function install() {
      xtc_db_query("insert into `gx_configurations` (`key`, `value`, `sort_order`, `type`) values ('configuration/MODULE_ORDER_TOTAL_COINSNAP_STATUS', 'true', '1','switcher')");
      xtc_db_query("insert into `gx_configurations` (`key`, `value`, `sort_order`) values ('configuration/MODULE_ORDER_TOTAL_COINSNAP_SORT_ORDER', '98', '2')");
    }
    
    public function isInstalled(){
        foreach ($this->keys() as $key) {
            if (!defined($key)) {
		return false;
            }
	}
	return true;
    }

    function remove() {
      xtc_db_query("delete from `gx_configurations` where `key` in ('" . implode("', '", $this->keys()) . "')");
    }
}
  
MainFactory::load_origin_class('ot_coinsnap');