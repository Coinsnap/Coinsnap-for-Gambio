<?php

class CoinsnapCheckoutConfirmationContentControl extends CoinsnapCheckoutConfirmationContentControl_parent
{
	public function proceed()
	{
		$choosenPaymentMethod = xtc_db_prepare_input($this->v_data_array['POST']['payment']) ?? '';
		if (strpos($choosenPaymentMethod, 'coinsnap') === false) {
			return parent::proceed();
		}
		
		$this->v_data_array['POST']['payment'] = 'coinsnap';
		parent::proceed();
		$_SESSION['choosen_payment_method'] = $choosenPaymentMethod;
	}
}
