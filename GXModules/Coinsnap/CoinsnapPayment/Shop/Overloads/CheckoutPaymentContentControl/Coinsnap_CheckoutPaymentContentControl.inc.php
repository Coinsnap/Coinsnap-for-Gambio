<?php

class Coinsnap_CheckoutPaymentContentControl extends Coinsnap_CheckoutPaymentContentControl_parent
{
    public function proceed(){
        $_SESSION['gm_error_message'] = $this->getErrorMessage();
        return parent::proceed();
    }

    // The former lookup of the transaction here came from another payment module: Settings::getApiClient()
    // creates a Coinsnap\ApiClient class that does not exist, so every customer who came back to the payment
    // page with an unpaid Coinsnap invoice in the session got a fatal error. It never produced a message.
    private function getErrorMessage(){
        return '';
    }
}
