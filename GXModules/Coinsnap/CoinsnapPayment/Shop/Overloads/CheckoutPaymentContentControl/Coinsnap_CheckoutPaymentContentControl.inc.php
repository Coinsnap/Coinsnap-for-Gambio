<?php

use GXModules\Library\Core\Settings\Struct\Settings;

class Coinsnap_CheckoutPaymentContentControl extends Coinsnap_CheckoutPaymentContentControl_parent
{
    public function proceed(){
        $_SESSION['gm_error_message'] = $this->getErrorMessage();
        return parent::proceed();
    }

    /**
     * @return string
     * @throws \Coinsnap\ApiException
     * @throws \Coinsnap\Http\ConnectionException
     * @throws \Coinsnap\VersioningException
     */
    private function getErrorMessage(){
        $transactionId = $_SESSION['transactionID'] ?? null;

	if (filter_input(INPUT_GET,'payment_error',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null || empty($transactionId)) {
            return '';
	}

	$settings = new Settings();
	    $transaction = $settings->getApiClient()->getTransactionService()->read($settings->getSpaceId(), $_SESSION['transactionID']);
	    $languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
	    if (!empty(filter_input(INPUT_GET,'payment_error',FILTER_SANITIZE_FULL_SPECIAL_CHARS))) {
	        return $languageTextManager->get_text(filter_input(INPUT_GET,'payment_error',FILTER_SANITIZE_FULL_SPECIAL_CHARS), 'coinsnap');
	    }

	return $transaction->getUserFailureMessage();
    }
}
