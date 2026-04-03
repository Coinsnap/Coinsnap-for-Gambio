<?php

class CoinsnapCheckoutConfirmationContentControl extends CoinsnapCheckoutConfirmationContentControl_parent {
    public function proceed(){
            
        $post_array = $this->v_data_array['POST'];
        
            $choosenPaymentMethod = (null !== $post_array && array_key_exists('payment',$post_array))? xtc_db_prepare_input($post_array['payment']) : '';
            if (strpos($choosenPaymentMethod, 'coinsnap') === false) {
                return parent::proceed();
            }

            $this->v_data_array['POST']['payment'] = 'coinsnap';
            parent::proceed();
            $_SESSION['choosen_payment_method'] = $choosenPaymentMethod;
    }
}
