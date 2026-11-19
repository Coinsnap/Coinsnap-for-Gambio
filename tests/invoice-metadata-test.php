<?php
// Checks the create-invoice request the checkout sends (orderId and metadata), for Coinsnap and for BTCPay.
// Run: php tests/invoice-metadata-test.php   (no Gambio installation, no network, no database)
declare(strict_types=1);

namespace Coinsnap\Http {
    error_reporting(E_ALL & ~E_DEPRECATED);
    foreach (['ResponseInterface', 'ClientInterface', 'Response'] as $file) {
        require_once dirname(__DIR__) . '/GXModules/Coinsnap/CoinsnapPayment/CoinsnapAPILibrary/Http/' . $file . '.php';
    }

    // Replaces the library's cURL client, so every API call is answered and recorded here.
    class CurlClient implements ClientInterface
    {
        public static array $routes = [];
        public static array $calls = [];

        public function setCurlOptions(array $options) {}

        public function request(string $method, string $url, array $headers = [], string $body = ''): ResponseInterface
        {
            self::$calls[] = [$method, $url, $headers, $body];
            foreach (self::$routes as [$m, $pattern, $status, $responseBody]) {
                if ($m === $method && preg_match($pattern, $url)) {
                    return new Response((int) $status, $responseBody, []);
                }
            }
            return new Response(404, '{}', []);
        }
    }
}

namespace {
    error_reporting(E_ALL & ~E_DEPRECATED);
    set_error_handler(function (int $no, string $str, string $file, int $line) {
        if ($no === E_DEPRECATED) {
            return true;
        }
        throw new ErrorException($str, 0, $no, $file, $line);
    });

    $module = dirname(__DIR__) . '/GXModules/Coinsnap/CoinsnapPayment';

    // ---- Gambio / xtc stubs ----
    class ConfigurationStorage
    {
        public static array $data = [];
        public function __construct(string $ns) {}
        public function get($key) { return self::$data[$key] ?? false; }
        public function get_all($prefix = '') { return self::$data; }
        public function set($key, $value) { self::$data[$key] = $value; return true; }
    }
    class MainFactory
    {
        public static function create($class, ...$args) { return $class === 'CoinsnapStorage' ? new CoinsnapStorage() : null; }
        public static function create_object($class, $args = [], $singleton = false) { return new LanguageTextManager(); }
    }
    class LanguageTextManager { public function get_text($key, $section = '') { return $key; } }
    class OrderTotalStub
    {
        public function output_array(): array
        {
            return [
                ['code' => 'ot_subtotal', 'title' => 'Zwischensumme:', 'value' => 109.0],
                ['code' => 'ot_shipping', 'title' => 'Versand:', 'value' => 4.9],
                ['code' => 'ot_coinsnap', 'title' => 'Bitcoin-Rabatt:', 'value' => -5.0],
                ['code' => 'ot_tax', 'title' => 'inkl. 19% MwSt.:', 'value' => 18.15],
                ['code' => 'ot_total', 'title' => 'Summe:', 'value' => 108.9],
            ];
        }
    }
    class Coinsnap_CheckoutProcessProcess_parent
    {
        public $coo_order;
        public $coo_order_total;
    }
    function xtc_href_link($page, $params = '') { return 'https://shop.example/' . $page; }
    function xtc_db_query($q) { return []; }
    function xtc_db_fetch_array($r) { return []; }
    function xtc_db_input($v) { return addslashes((string) $v); }

    define('COINSNAP_SERVER_PATH', 'stores');
    define('COINSNAP_REFERRAL_CODE', 'TEST');
    define('HTTPS_SERVER', 'https://shop.example');
    define('DIR_WS_CATALOG', '/');

    require $module . '/loader.php';
    require $module . '/Library/Core/Storage/CoinsnapStorage.inc.php';
    require $module . '/Shop/Overloads/CheckoutProcessProcess/Coinsnap_CheckoutProcessProcess.inc.php';

    use Coinsnap\Http\CurlClient;

    $passed = 0;
    $failed = 0;
    function check(string $name, bool $ok, $detail = null): void
    {
        global $passed, $failed;
        $ok ? $passed++ : $failed++;
        echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok || $detail === null ? '' : ' -- got ' . json_encode($detail, JSON_UNESCAPED_UNICODE)) . "\n";
    }

    // The checkout order object as Gambio builds it (cast to an array in getInvoiceUrl()).
    $order = new stdClass();
    $order->info = ['total' => '108.90', 'currency' => 'EUR', 'comments' => 'Bitte klingeln', 'shipping_method' => 'DHL Paket', 'payment_method' => 'coinsnap', 'tax' => 18.15, 'shipping_cost' => 4.9];
    $order->customer = ['firstname' => 'Erika', 'lastname' => 'Mustermann', 'email_address' => 'erika@example.com', 'telephone' => '+49 30 123456'];
    $order->billing = ['firstname' => 'Erika', 'lastname' => 'Mustermann', 'company' => 'Muster &amp; Co', 'street_address' => 'Hauptstr.', 'house_number' => '5', 'additional_address_info' => 'Hinterhaus', 'suburb' => '', 'city' => 'Berlin', 'postcode' => '10115', 'state' => '', 'country' => ['title' => 'Deutschland', 'iso_code_2' => 'DE']];
    $order->products = [
        ['qty' => 2, 'name' => 'Tasse', 'model' => 'MUG-1', 'id' => '7', 'price' => 19.5],
        ['qty' => 1, 'name' => 'T-Shirt', 'model' => '', 'id' => '9{3}12', 'price' => 70.0],
    ];

    function invoiceRequest(string $provider, $order): array
    {
        ConfigurationStorage::$data = $provider === 'btcpay'
            ? ['provider' => 'btcpay', 'btcpay_serverurl' => 'https://btcpay.example', 'btcpay_storeid' => 'BTC-STORE-1', 'btcpay_apikey' => 'k', 'autoredirect' => 1]
            : ['provider' => 'coinsnap', 'storeid' => 'CS-STORE-1', 'apikey' => 'k', 'autoredirect' => 1];
        CurlClient::$calls = [];
        CurlClient::$routes = [
            ['GET', '#coingecko#', 200, '{"rates":{"eur":{"value":90000},"usd":{"value":100000}}}'],
            ['GET', '#/payment-methods$#', 200, '[{"enabled":true,"paymentMethodId":"BTC-LN"}]'],
            ['GET', '#/rates\?#', 200, '[{"currencyPair":"BTC_EUR","rate":"90000"}]'],
            ['POST', '#/invoices$#', 200, '{"id":"INV-1","checkoutLink":"https://pay.example/i/INV-1"}'],
        ];
        $p = new Coinsnap_CheckoutProcessProcess();
        $p->coo_order = $order;
        $p->coo_order_total = new OrderTotalStub();
        $ref = new ReflectionProperty($p, 'configuration');
        $ref->setAccessible(true);
        $ref->setValue($p, new CoinsnapStorage());
        $url = $p->getInvoiceUrl('4711');
        foreach (CurlClient::$calls as [$method, $u, , $body]) {
            if ($method === 'POST' && preg_match('#/invoices$#', $u)) {
                return ['url' => $url, 'body' => json_decode($body, true)];
            }
        }
        return ['url' => $url, 'body' => null];
    }

    foreach (['coinsnap', 'btcpay'] as $provider) {
        $r = invoiceRequest($provider, clone $order);
        $b = $r['body'] ?? [];
        $m = $b['metadata'] ?? [];
        check("$provider: invoice created", $r['url'] === 'https://pay.example/i/INV-1');
        check("$provider: top-level orderId is the order number", ($b['orderId'] ?? null) === '4711', $b['orderId'] ?? null);
        check("$provider: top-level buyerEmail", ($b['buyerEmail'] ?? null) === 'erika@example.com');
        check("$provider: metadata orderId / orderNumber unchanged", ($m['orderId'] ?? null) === '4711' && ($m['orderNumber'] ?? null) === '4711', $m);
        check("$provider: orderUrl", ($m['orderUrl'] ?? null) === 'https://shop.example/admin/orders.php?oID=4711&action=edit', $m['orderUrl'] ?? null);
        check("$provider: customerName (as before)", ($m['customerName'] ?? null) === 'Erika Mustermann');
        check("$provider: buyer contact", ($m['buyerName'] ?? null) === 'Erika Mustermann' && ($m['buyerEmail'] ?? null) === 'erika@example.com' && ($m['buyerPhone'] ?? null) === '+49 30 123456', $m);
        check("$provider: billing address", ($m['buyerAddress1'] ?? null) === 'Hauptstr. 5' && ($m['buyerAddress2'] ?? null) === 'Hinterhaus'
            && ($m['buyerCity'] ?? null) === 'Berlin' && ($m['buyerZip'] ?? null) === '10115' && ($m['buyerCountry'] ?? null) === 'DE'
            && ($m['buyerCompany'] ?? null) === 'Muster & Co' && !array_key_exists('buyerState', $m), $m);
        check("$provider: customer note", ($m['buyerMessage'] ?? null) === 'Bitte klingeln');
        // JSON numbers: 2.0 comes back as 2, so numbers are compared by value.
        check("$provider: line items", ($m['lineItems'] ?? null) == [
            ['name' => 'Tasse', 'sku' => 'MUG-1', 'productId' => '7', 'quantity' => 2.0, 'price' => 19.5, 'total' => 39.0],
            ['name' => 'T-Shirt', 'productId' => '9{3}12', 'quantity' => 1.0, 'price' => 70.0, 'total' => 70.0],
        ], $m['lineItems'] ?? null);
        check("$provider: itemDesc / itemCode", ($m['itemDesc'] ?? null) === '2 x Tasse, T-Shirt' && ($m['itemCode'] ?? null) === 'MUG-1, 9{3}12', [$m['itemDesc'] ?? null, $m['itemCode'] ?? null]);
        check("$provider: totals", ($m['subtotal'] ?? null) == 109 && ($m['shippingTotal'] ?? null) == 4.9 && ($m['taxTotal'] ?? null) == 18.15
            && ($m['discountTotal'] ?? null) == 5 && ($m['orderTotal'] ?? null) == 108.9 && ($m['currency'] ?? null) === 'EUR', $m);
        check("$provider: platform / shipping method", ($m['platform'] ?? null) === 'gambio' && ($m['shippingMethod'] ?? null) === 'DHL Paket');
        check("$provider: no empty values", !in_array('', $m, true) && !in_array(null, $m, true));
    }

    // A guest order without billing block or products still creates the invoice.
    $bare = new stdClass();
    $bare->info = ['total' => '10.00', 'currency' => 'EUR'];
    $bare->customer = ['firstname' => 'A', 'lastname' => 'B', 'email_address' => 'a@example.com'];
    $r = invoiceRequest('coinsnap', $bare);
    check('sparse order: invoice created with orderId', $r['url'] === 'https://pay.example/i/INV-1' && ($r['body']['orderId'] ?? null) === '4711'
        && ($r['body']['metadata']['buyerName'] ?? null) === 'A B' && !isset($r['body']['metadata']['lineItems']), $r['body']['metadata'] ?? null);

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
