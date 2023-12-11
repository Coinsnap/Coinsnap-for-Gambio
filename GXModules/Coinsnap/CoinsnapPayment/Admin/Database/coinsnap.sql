CREATE TABLE `coinsnap_transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `transaction_id` text NOT NULL,
    `data` json NOT NULL,
    `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `order_id` text NOT NULL,
    `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` datetime(3) NOT NULL,
    `updated_at` datetime(3) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `coinsnap_callback` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `header` json NOT NULL,
    `body` json NOT NULL,
    `created_at` datetime(3) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;