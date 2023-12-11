<?php declare(strict_types=1);

use Coinsnap\Result\TransactionState;
use GXModules\Shop\Classes\Model\CoinsnapTransactionModel;

class Coinsnap_OrderExtender extends Coinsnap_OrderExtender_parent {
    public function proceed(){
	require(DIR_FS_CATALOG . DIR_WS_CLASSES . 'xtcPrice.php');
	$xtPrice = new xtcPrice(DEFAULT_CURRENCY, $_SESSION['customers_status']['customers_status_id']);

	$contentView = MainFactory::create('ContentView');
	$contentView->set_template_dir(DIR_FS_DOCUMENT_ROOT);
	$contentView->set_content_template('GXModules/Coinsnap/CoinsnapPayment/Admin/Templates/coinsnap_transaction_panel.html');
	$contentView->set_flat_assigns(true);
	$contentView->set_caching_enabled(false);

	$transactionModel = new CoinsnapTransactionModel();
	$orderId = (int)$_GET['oID'];

	$transaction = $transactionModel->getByOrderId($orderId);

	$transactionData = $transaction->getData();
	$transactionInfo = $transactionData ? \json_decode($transactionData, true) : [];
	$transactionState = $transaction->getState();
	$contentView->set_content_data('orderId', $orderId);

	$contentView->set_content_data('xtPrice', $xtPrice);
	$contentView->set_content_data('totalOrderAmount', round($transactionInfo['info']['total'], 2));
	$contentView->set_content_data('transactionState', $transactionState);
	$contentView->set_content_data('processingState', TransactionState::PROCESSING);
	$contentView->set_content_data('settledState', TransactionState::SETTLED);
        $contentView->set_content_data('showButtonsAfterFullfill', $showButtonsAfterFullfill);

	$languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
	$this->v_output_buffer['below_product_data_heading'] = 'Coinsnap ' . $languageTextManager->get_text('transaction_panel', 'coinsnap');
	$this->v_output_buffer['below_product_data'] = $contentView->get_html();

	$this->addContent();
        parent::proceed();
    }
}
