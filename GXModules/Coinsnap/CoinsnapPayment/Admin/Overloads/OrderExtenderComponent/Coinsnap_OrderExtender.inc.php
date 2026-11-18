<?php declare(strict_types=1);

use GXModules\Shop\Classes\Model\CoinsnapTransactionModel;

// Read-only Coinsnap panel on the order page; Coinsnap payments cannot be captured, voided or refunded from here.
class Coinsnap_OrderExtender extends Coinsnap_OrderExtender_parent {
    public function proceed(){
        // Raw row: the entity casts the invoice id to int.
        $row = (new CoinsnapTransactionModel())->getFromDbByOrderId((int) ($_GET['oID'] ?? 0));
        if (!empty($row)) {
            $this->addCoinsnapPanel($row);
        }
        parent::proceed();
    }

    private function addCoinsnapPanel(array $row): void {
        require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'xtcPrice.php');
        $xtPrice = new xtcPrice(DEFAULT_CURRENCY, $_SESSION['customers_status']['customers_status_id']);
        $info = json_decode((string) ($row['data'] ?? ''), true);

        $contentView = MainFactory::create('ContentView');
        $contentView->set_template_dir(DIR_FS_DOCUMENT_ROOT);
        $contentView->set_content_template('GXModules/Coinsnap/CoinsnapPayment/Admin/Templates/coinsnap_transaction_panel.html');
        $contentView->set_flat_assigns(true);
        $contentView->set_caching_enabled(false);
        $contentView->set_content_data('xtPrice', $xtPrice);
        $contentView->set_content_data('invoiceId', (string) ($row['transaction_id'] ?? ''));
        $contentView->set_content_data('transactionState', (string) ($row['state'] ?? ''));
        $contentView->set_content_data('totalOrderAmount', round((float) ($info['info']['total'] ?? 0), 2));

        $languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
        $this->v_output_buffer['below_product_data_heading'] = 'Coinsnap ' . $languageTextManager->get_text('transaction_panel', 'coinsnap');
        $this->v_output_buffer['below_product_data'] = $contentView->get_html();
        $this->addContent();
    }
}
