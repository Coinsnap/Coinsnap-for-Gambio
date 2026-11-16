<?php

declare(strict_types=1);

namespace GXModules\Library\Webhook;

// Amount and currency sent for each invoice this module created, and whether it was applied; table made on first use.
class InvoiceRecord
{
    private const TABLE = 'coinsnap_invoice_expected';

    public function __construct()
    {
        xtc_db_query('CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
            `invoice_id` VARCHAR(64) NOT NULL,
            `order_id` INT UNSIGNED NOT NULL,
            `amount` VARCHAR(32) NOT NULL,
            `currency` VARCHAR(8) NOT NULL,
            `paid_at` DATETIME NULL,
            `claim` CHAR(32) NULL,
            PRIMARY KEY (`invoice_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function remember(string $invoiceId, int $orderId, string $amount, string $currency): void
    {
        xtc_db_query("INSERT IGNORE INTO `" . self::TABLE . "` (`invoice_id`, `order_id`, `amount`, `currency`) VALUES ('"
            . xtc_db_input($invoiceId) . "', " . (int) $orderId . ", '" . xtc_db_input($amount) . "', '" . xtc_db_input($currency) . "')");
    }

    public function find(string $invoiceId, int $orderId): ?array
    {
        $row = xtc_db_fetch_array(xtc_db_query("SELECT `amount`, `currency` FROM `" . self::TABLE . "` WHERE `invoice_id` = '"
            . xtc_db_input($invoiceId) . "' AND `order_id` = " . (int) $orderId));
        return is_array($row) && $row !== [] ? $row : null;
    }

    // Whether any invoice has already paid this order.
    public function orderIsPaid(int $orderId): bool
    {
        $row = xtc_db_fetch_array(xtc_db_query("SELECT 1 AS paid FROM `" . self::TABLE . "` WHERE `order_id` = " . (int) $orderId . " AND `paid_at` IS NOT NULL LIMIT 1"));
        return is_array($row) && $row !== [];
    }

    // True for the first caller only, so two webhooks for one invoice can't both settle the order.
    public function claimPaid(string $invoiceId, int $orderId, string $amount, string $currency): bool
    {
        $id = xtc_db_input($invoiceId);
        xtc_db_query("INSERT IGNORE INTO `" . self::TABLE . "` (`invoice_id`, `order_id`, `amount`, `currency`) VALUES ('"
            . $id . "', " . (int) $orderId . ", '" . xtc_db_input($amount) . "', '" . xtc_db_input($currency) . "')");

        // The update only lands while unclaimed; reading the token back shows whether this caller won.
        $token = bin2hex(random_bytes(16));
        xtc_db_query("UPDATE `" . self::TABLE . "` SET `paid_at` = NOW(), `claim` = '" . $token . "' WHERE `invoice_id` = '"
            . $id . "' AND `order_id` = " . (int) $orderId . " AND `paid_at` IS NULL");
        $row = xtc_db_fetch_array(xtc_db_query("SELECT `claim` FROM `" . self::TABLE . "` WHERE `invoice_id` = '" . $id . "'"));
        return is_array($row) && ($row['claim'] ?? '') === $token;
    }
}
