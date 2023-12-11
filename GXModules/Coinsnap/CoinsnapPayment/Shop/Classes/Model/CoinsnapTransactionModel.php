<?php declare(strict_types=1);

namespace GXModules\Shop\Classes\Model;

use GXModules\Library\Core\Settings\Struct\Settings;
use Coinsnap\Result\TransactionState;
use GXModules\Shop\Classes\Entity\CoinsnapTransactionEntity;

class CoinsnapTransactionModel {
    public const TRANSACTION_STATE_NEW = 'New';
    public const TRANSACTION_STATE_EXPIRED = 'Expired';
    public const TRANSACTION_STATE_SETTLED = 'Settled';
    public const TRANSACTION_STATE_PROCESSING = 'Processing';

    public function getByOrderId(int $orderId): ?CoinsnapTransactionEntity {
        
        $orderData = $this->getFromDbByOrderId($orderId);
        if (empty($orderData)) {
            return null;
        }
        
        return new CoinsnapTransactionEntity($orderData);
    }

    /**
	* @param Settings $settings
	* @param string $transactionId
	* @param string $orderId
	* @param array $orderData
    */
    public function create(Settings $settings, string $transactionId, string $orderId, array $orderData): void {
        $insertData = [
            'transaction_id' => $transactionId,
            'data' => json_encode($orderData),
            'payment_method' => $orderData['info']['payment_method'],
            'order_id' => $orderId,
            'state' => TransactionState::NEW,
            'created_at' => date('Y-m-d H:i:s')
	];
	xtc_db_perform('coinsnap_transactions', $insertData, 'insert');
    }

    /**
	* @param string $newStatus
	* @param int $orderId
	* @throws \Exception
    */
    public function updateTransactionStatus(string $newStatus, int $orderId): void {
        xtc_db_perform('coinsnap_transactions',['state' => $newStatus],'update','order_id = ' . xtc_db_input($orderId));
    }

    /**
	* @param int $orderId
	* @return array
    */
    public function getFromDbByOrderId(int $orderId): array {
        $query = xtc_db_query("SELECT * FROM `coinsnap_transactions` WHERE order_id = '" . xtc_db_input($orderId)."'");
        return xtc_db_fetch_array($query);
    }
    
    /**
	* @param int $orderId
	* @return array
    */
    public function getFromDbByTransactionId(string $transactionId): array {
        $query = xtc_db_query("SELECT * FROM `coinsnap_transactions` WHERE transaction_id = '" . xtc_db_input($transactionId)."'");
        return xtc_db_fetch_array($query);
    }
}