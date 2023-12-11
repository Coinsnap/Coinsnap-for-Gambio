<?php

class Coinsnap_payment extends Coinsnap_payment_parent
{
	public function __construct($module = '')
	{
		$payment = $_SESSION['payment'];
		parent::__construct($module);
		if (strpos(strtolower($payment), 'coinsnap') !== false) {
			$_SESSION['payment'] = $payment;
		}
	}
}
