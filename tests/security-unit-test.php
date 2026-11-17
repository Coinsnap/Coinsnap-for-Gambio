<?php
// Unit tests for the security-relevant code, with stubs for the Gambio/xtc classes it touches.
// Run: php tests/security-unit-test.php   (no Gambio installation, no network, no database)
declare(strict_types=1);

namespace Coinsnap\Http {
    error_reporting(E_ALL & ~E_DEPRECATED);
    foreach (['ResponseInterface', 'ClientInterface', 'Response'] as $file) {
        require_once dirname(__DIR__) . '/GXModules/Coinsnap/CoinsnapPayment/CoinsnapAPILibrary/Http/' . $file . '.php';
    }
    require_once dirname(__DIR__) . '/GXModules/Coinsnap/CoinsnapPayment/CoinsnapAPILibrary/Exception/CSException.php';
    require_once dirname(__DIR__) . '/GXModules/Coinsnap/CoinsnapPayment/CoinsnapAPILibrary/Exception/ConnectException.php';

    // Replaces the library's cURL client before the autoloader loads it, so every API call is answered here.
    class CurlClient implements ClientInterface
    {
        /** @var array<int, array{0:string,1:string,2:int|string,3:string}> method, URL pattern, status (or 'down'), body */
        public static array $routes = [];
        public static array $calls = [];

        public function setCurlOptions(array $options) {}

        public function request(string $method, string $url, array $headers = [], string $body = ''): ResponseInterface
        {
            self::$calls[] = [$method, $url, $headers, $body];
            foreach (self::$routes as [$m, $pattern, $status, $responseBody]) {
                if ($m === $method && preg_match($pattern, $url)) {
                    if ($status === 'down') {
                        throw new \Coinsnap\Exception\ConnectException('Connection refused', 7);
                    }
                    return new Response((int) $status, $responseBody, []);
                }
            }
            return new Response(404, '{}', []);
        }
    }
}

namespace {
    // Deprecations of the bundled library under newer PHP versions (implicitly nullable parameters) are not failures here.
    error_reporting(E_ALL & ~E_DEPRECATED);
    set_error_handler(function (int $no, string $str, string $file, int $line) {
        if ($no === E_DEPRECATED) {
            return true;
        }
        // Notices from code this PR did not touch (undefined variables in the save handler) are not failures here.
        if (strpos($file, 'CoinsnapModuleCenterModuleController') !== false && strpos($str, 'Undefined variable') !== false) {
            return true;
        }
        throw new ErrorException($str, 0, $no, $file, $line);
    });

    $root = dirname(__DIR__);
    $module = $root . '/GXModules/Coinsnap/CoinsnapPayment';

    // ---- Gambio / xtc stubs ----
    class ConfigurationStorage
    {
        public static array $data = [];
        private string $ns;
        public function __construct(string $ns) { $this->ns = $ns; }
        public function get($key) { return self::$data[$key] ?? false; }
        public function get_all($prefix = '') { return self::$data; }
        public function set($key, $value) { self::$data[$key] = $value; return true; }
    }
    class MainFactory
    {
        public static function create($class, ...$args)
        {
            if ($class === 'CoinsnapStorage') {
                return new CoinsnapStorage();
            }
            return (object) ['class' => $class, 'args' => $args];
        }
        public static function create_object($class, $args = [], $singleton = false) { return new LanguageTextManager(); }
        public static function load_origin_class($c) {}
    }
    class LanguageTextManager { public function get_text($key, $section = '') { return $key; } }
    class MessageStack
    {
        public array $messages = [];
        public function add_session($m, $t) { $this->messages[] = [$t, $m]; }
        public function add($c, $m, $t = '') { $this->messages[] = [$t, $m]; }
    }
    class HttpViewController {}
    class PageTokenRefused extends Exception {}
    class AbstractModuleCenterModuleController
    {
        public $languageTextManager;
        public static array $post = [];
        public static bool $tokenValid = true;
        public function __construct() { $this->languageTextManager = new LanguageTextManager(); $this->_init(); }
        protected function _init(): void {}
        protected function _validatePageToken() { if (!self::$tokenValid) { throw new PageTokenRefused('invalid page token'); } }
        protected function _getPostData($key) { return self::$post[$key] ?? null; }
    }
    class NonEmptyStringType { public function __construct($v) {} }
    function xtc_catalog_href_link($page, $params = '') { return 'https://shop.example/' . $page . '?' . $params; }
    function xtc_href_link($page, $params = '') { return 'https://shop.example/admin/' . $page . '?' . $params; }
    function xtc_db_query($q) { return []; }
    function xtc_db_fetch_array($r) { return []; }
    function xtc_db_input($v) { return addslashes((string) $v); }
    function xtc_db_perform(...$a) {}

    define('COINSNAP_SERVER_PATH', 'stores');
    define('COINSNAP_REFERRAL_CODE', 'TEST');
    define('DEFAULT_ORDERS_STATUS_ID', 1);

    require $module . '/loader.php';
    require $module . '/Library/Core/Storage/CoinsnapStorage.inc.php';
    require $module . '/Shop/Classes/Controller/CoinsnapWebhookController.inc.php';
    require $module . '/Admin/Classes/Controller/CoinsnapModuleCenterModuleController.inc.php';

    use Coinsnap\Http\CurlClient;
    use GXModules\Library\Btcpay\KeyPermissions;
    use GXModules\Library\Webhook\InvoiceDecision;
    use GXModules\Library\Webhook\InvoiceRecord;
    use GXModules\Library\Webhook\Signature;
    use GXModules\Shop\Classes\Model\CoinsnapTransactionModel;

    $passed = 0;
    $failed = 0;
    function check(string $name, bool $ok): void
    {
        global $passed, $failed;
        $ok ? $passed++ : $failed++;
        echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    }

    // ---- Fakes for the webhook controller ----
    class Replied extends Exception {}
    class FakeTransactions extends CoinsnapTransactionModel
    {
        public array $rows = [];
        public function getFromDbByTransactionId(string $transactionId): array { return $this->rows[$transactionId] ?? []; }
    }
    class FakeInvoiceRecord extends InvoiceRecord
    {
        public array $rows = [];
        public function __construct() {}
        public function remember(string $invoiceId, int $orderId, string $amount, string $currency): void
        {
            $this->rows[$invoiceId] = $this->rows[$invoiceId] ?? ['order_id' => $orderId, 'amount' => $amount, 'currency' => $currency, 'paid_at' => null, 'claim' => null];
        }
        public function find(string $invoiceId, int $orderId): ?array
        {
            $r = $this->rows[$invoiceId] ?? null;
            return $r !== null && $r['order_id'] === $orderId ? ['amount' => $r['amount'], 'currency' => $r['currency']] : null;
        }
        public function orderIsPaid(int $orderId): bool
        {
            foreach ($this->rows as $r) {
                if ($r['order_id'] === $orderId && $r['paid_at'] !== null) {
                    return true;
                }
            }
            return false;
        }
        public function claimPaid(string $invoiceId, int $orderId, string $amount, string $currency): ?string
        {
            $this->remember($invoiceId, $orderId, $amount, $currency);
            if ($this->rows[$invoiceId]['paid_at'] !== null) {
                return null;
            }
            $token = bin2hex(random_bytes(16));
            $this->rows[$invoiceId]['paid_at'] = 'now';
            $this->rows[$invoiceId]['claim'] = $token;
            return $token;
        }
        public function releaseClaim(string $invoiceId, string $token): void
        {
            if (($this->rows[$invoiceId]['claim'] ?? null) === $token) {
                $this->rows[$invoiceId]['paid_at'] = null;
                $this->rows[$invoiceId]['claim'] = null;
            }
        }
    }
    class TestWebhookController extends CoinsnapWebhookController
    {
        public string $body = '';
        public FakeTransactions $tx;
        public FakeInvoiceRecord $records;
        public array $orderStatus = [];      // order id => shop status id
        public array $statusUpdates = [];    // [order id, new state]
        public array $log = [];
        public bool $failOrderUpdate = false;
        public const STATUS_IDS = ['new' => 10, 'processing' => 11, 'expired' => 12, 'settled' => 13];

        protected function rawBody(): string { return $this->body; }
        protected function reply(int $status, string $message): void { throw new Replied((string) $status); }
        protected function transactions(): CoinsnapTransactionModel { return $this->tx; }
        protected function invoiceRecord(): InvoiceRecord { return $this->records; }
        protected function writeLog(string $level, string $line): void { $this->log[] = $line; }
        protected function shopOrderStatus(int $orderId): int { return $this->orderStatus[$orderId] ?? 0; }
        protected function getOrderStatusId(string $name): int { return self::STATUS_IDS[strtolower($name)] ?? 0; }
        protected function updateOrderAndTransactionStatus(string $newStatus, int $orderId): void
        {
            if ($this->failOrderUpdate) {
                throw new RuntimeException('database gone');
            }
            $this->statusUpdates[] = [$orderId, $newStatus];
            $this->orderStatus[$orderId] = self::STATUS_IDS[strtolower($newStatus)];
            foreach ($this->tx->rows as $id => $row) {
                if ((int) $row['order_id'] === $orderId) {
                    $this->tx->rows[$id]['state'] = $newStatus;
                }
            }
        }

        // Sends one webhook and returns the HTTP status the controller chose.
        public function send(string $body, array $headers): int
        {
            $this->body = $body;
            foreach (['HTTP_X_COINSNAP_SIGNATURE', 'HTTP_BTCPAY_SIG'] as $h) {
                unset($_SERVER[$h]);
            }
            foreach ($headers as $k => $v) {
                $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
            }
            try {
                $this->actionIndex();
            } catch (Replied $r) {
                return (int) $r->getMessage();
            } catch (Throwable $e) {
                if (getenv('DEBUG')) { echo get_class($e), ': ', $e->getMessage(), ' @ ', $e->getFile(), ':', $e->getLine(), "\n"; }
                return 500;
            }
            return 0;
        }
    }

    function coinsnapHeader(string $body, string $secret, ?int $t = null): array
    {
        $t = $t ?? time();
        return ['X-Coinsnap-Signature' => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret)];
    }
    function btcpayHeader(string $body, string $secret): array
    {
        return ['BTCPay-Sig' => 'sha256=' . hash_hmac('sha256', $body, $secret)];
    }
    function invoiceRoute(string $invoiceId, int|string $status, array $invoice = []): array
    {
        return ['GET', '#/stores/[^/]+/invoices/' . preg_quote($invoiceId, '#') . '$#', $status, json_encode($invoice)];
    }
    function freshWebhookSetup(string $provider = 'coinsnap'): TestWebhookController
    {
        ConfigurationStorage::$data = [
            'provider' => $provider, 'storeid' => 'CS-STORE-1', 'apikey' => 'cs-key-1234',
            'btcpay_serverurl' => 'https://btcpay.example', 'btcpay_storeid' => 'BTC-STORE-1', 'btcpay_apikey' => 'btc-key-5678',
        ];
        $storeId = $provider === 'btcpay' ? 'BTC-STORE-1' : 'CS-STORE-1';
        (new CoinsnapStorage())->setWebhook($provider, $storeId, ['id' => 'wh1', 'secret' => 'store-secret', 'url' => 'https://shop.example/shop.php']);
        CurlClient::$routes = [];
        CurlClient::$calls = [];
        $c = new TestWebhookController();
        $c->configuration = new CoinsnapStorage();
        $c->tx = new FakeTransactions();
        $c->records = new FakeInvoiceRecord();
        // Order 42, paid with invoice inv_1 for 100.00 EUR (recorded at checkout).
        $c->tx->rows['inv_1'] = ['order_id' => '42', 'state' => 'New', 'created_at' => date('Y-m-d H:i:s'), 'data' => json_encode(['info' => ['total' => 100, 'currency' => 'EUR']])];
        $c->records->remember('inv_1', 42, '100.00', 'EUR');
        $c->orderStatus[42] = 10;
        return $c;
    }

    // ===== Signature =====
    $now = 1_800_000_000;
    $b = '{"invoiceId":"x"}';
    $sig = fn (int $t, string $secret) => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $b, $secret);
    check('signature: valid Coinsnap signature', Signature::coinsnapProblem($sig($now, 's'), $b, 's', $now) === null);
    check('signature: wrong secret is "bad signature"', Signature::coinsnapProblem($sig($now, 'x'), $b, 's', $now) === Signature::BAD_SIGNATURE);
    check('signature: missing header', Signature::coinsnapProblem('', $b, 's', $now) === Signature::BAD_SIGNATURE);
    check('signature: 10 minutes old is "stale timestamp"', Signature::coinsnapProblem($sig($now - 600, 's'), $b, 's', $now) === Signature::STALE_TIMESTAMP);
    check('signature: empty secret rejects even a matching HMAC', Signature::coinsnapProblem($sig($now, ''), $b, '', $now) === Signature::BAD_SIGNATURE);
    check('signature: valid BTCPay signature', Signature::btcpayProblem('sha256=' . hash_hmac('sha256', $b, 's'), $b, 's') === null);
    check('signature: BTCPay without sha256= prefix refused', Signature::btcpayProblem(hash_hmac('sha256', $b, 's'), $b, 's') !== null);
    check('signature: BTCPay empty secret refused', Signature::btcpayProblem('sha256=' . hash_hmac('sha256', $b, ''), $b, '') !== null);

    // ===== InvoiceDecision =====
    $settled = ['status' => 'Settled', 'invoiceAmount' => '100.00', 'currency' => 'EUR', 'amount' => 150000];
    check('decision: settled for the recorded amount is paid', InvoiceDecision::decide($settled, false, '100.00', 'EUR') === InvoiceDecision::PAID);
    check('decision: underpaid is a mismatch', InvoiceDecision::decide(['invoiceAmount' => '99.00'] + $settled, false, '100.00', 'EUR') === InvoiceDecision::MISMATCH);
    check('decision: other currency is a mismatch', InvoiceDecision::decide(['currency' => 'USD'] + $settled, false, '100.00', 'EUR') === InvoiceDecision::MISMATCH);
    check('decision: zero price is never paid', InvoiceDecision::decide(['invoiceAmount' => '0'] + $settled, false, '0.00', 'EUR') === InvoiceDecision::MISMATCH);
    check('decision: BTCPay "Marked" refused', InvoiceDecision::decide(['status' => 'Settled', 'additionalStatus' => 'Marked', 'amount' => '100.00', 'currency' => 'EUR'], true, '100.00', 'EUR') === InvoiceDecision::MISMATCH);
    check('decision: Expired', InvoiceDecision::decide(['status' => 'Expired'], false, '100.00', 'EUR') === InvoiceDecision::EXPIRED);
    check('decision: unknown status changes nothing', InvoiceDecision::decide(['status' => 'Invalid'], false, '100.00', 'EUR') === InvoiceDecision::NONE);

    // ===== BTCPay key permissions =====
    check('btcpay key: store permissions for this store accepted', KeyPermissions::acceptable(['btcpay.store.cancreateinvoice:S1', 'btcpay.store.canviewinvoices:S1'], 'S1'));
    check('btcpay key: unrestricted refused', !KeyPermissions::acceptable(['unrestricted'], 'S1'));
    check('btcpay key: permission for all stores refused', !KeyPermissions::acceptable(['btcpay.store.cancreateinvoice'], 'S1'));
    check('btcpay key: other store refused', !KeyPermissions::acceptable(['btcpay.store.cancreateinvoice:S2'], 'S1'));
    check('btcpay key: server permission refused', !KeyPermissions::acceptable(['btcpay.store.cancreateinvoice:S1', 'btcpay.server.canmodifyserversettings'], 'S1'));
    check('btcpay key: no permissions refused', !KeyPermissions::acceptable([], 'S1'));
    check('btcpay url: file:// refused', !KeyPermissions::isServerUrl('file:///etc/passwd'));
    check('btcpay url: https accepted', KeyPermissions::isServerUrl('https://btcpay.example'));
    CurlClient::$routes = [['GET', '#^https://btcpay\.example/api/v1/api-keys/current$#', 200, '{"permissions":["btcpay.store.canviewinvoices:S1"]}']];
    CurlClient::$calls = [];
    check('btcpay key: permissions are asked from BTCPay', KeyPermissions::fetch('https://btcpay.example/', 'k') === ['btcpay.store.canviewinvoices:S1']
        && (CurlClient::$calls[0][2]['Authorization'] ?? '') === 'token k');
    CurlClient::$routes = [['GET', '#api-keys/current#', 'down', '']];
    check('btcpay key: unreachable server gives no permissions', KeyPermissions::fetch('https://btcpay.example', 'k') === null);

    // ===== Storage: per-store webhook secret, settings that can be turned off =====
    ConfigurationStorage::$data = ['webhook' => json_encode(['id' => 'old', 'secret' => 'legacy-secret', 'url' => 'u'])];
    $st = new CoinsnapStorage();
    check('storage: webhook key differs per provider', CoinsnapStorage::webhookKey('coinsnap', 'S1') !== CoinsnapStorage::webhookKey('btcpay', 'S1'));
    check('storage: webhook key differs per store', CoinsnapStorage::webhookKey('coinsnap', 'S1') !== CoinsnapStorage::webhookKey('coinsnap', 'S2'));
    check('storage: webhook key does not contain the store ID', strpos(CoinsnapStorage::webhookKey('coinsnap', 'STORE-XYZ'), 'STORE-XYZ') === false);
    check('storage: pre-1.2.0 secret used until the settings are saved', ($st->getWebhook('coinsnap', 'S1', true)['secret'] ?? '') === 'legacy-secret');
    check('storage: admin does not treat the old shared record as this store\'s', $st->getWebhook('coinsnap', 'S1') === null);
    $st->setWebhook('coinsnap', 'S1', ['id' => 'a', 'secret' => 'secret-S1', 'url' => 'u']);
    $st->setWebhook('btcpay', 'S2', ['id' => 'b', 'secret' => 'secret-S2', 'url' => 'u']);
    check('storage: two stores keep their own secrets', $st->getWebhook('coinsnap', 'S1')['secret'] === 'secret-S1' && $st->getWebhook('btcpay', 'S2')['secret'] === 'secret-S2');
    check('storage: saving a per-store record clears the old shared one', $st->getWebhook('coinsnap', 'S3', true) === null);
    $st->set('discount_enabled', '1');
    $st->set('discount_enabled', '0');
    check('storage: a setting can be turned off ("0" is stored)', $st->get('discount_enabled') === '0');
    $st->set('btcpay_apikey', 'k');
    $st->set('btcpay_apikey', '');
    check('storage: a key can be cleared ("" is stored)', $st->get('btcpay_apikey') === '');

    // ===== Webhook controller =====
    $paid = ['id' => 'inv_1', 'status' => 'Settled', 'invoiceAmount' => '100.00', 'currency' => 'EUR', 'amount' => 150000];
    $body = json_encode(['type' => 'Settled', 'invoiceId' => 'inv_1']);

    $c = freshWebhookSetup();
    check('webhook: no signature -> 401', $c->send($body, []) === 401);
    check('webhook: wrong signature -> 401', $c->send($body, coinsnapHeader($body, 'wrong')) === 401);
    check('webhook: 10 min old timestamp -> 401', $c->send($body, coinsnapHeader($body, 'store-secret', time() - 600)) === 401);
    check('webhook: rejected requests make no API call', CurlClient::$calls === []);
    check('webhook: SECURITY lines for bad signature and stale timestamp', count(preg_grep('/SECURITY webhook reason=bad signature/', $c->log)) === 2 && count(preg_grep('/SECURITY webhook reason=stale timestamp/', $c->log)) === 1);
    check('webhook: secret of another store refused', $c->send($body, coinsnapHeader($body, 'secret-S1')) === 401);

    $c = freshWebhookSetup();
    ConfigurationStorage::$data['storeid'] = 'CS-STORE-2';      // same shop, other store: has no record yet
    check('webhook: store without its own secret and no old record -> 401', $c->send($body, coinsnapHeader($body, 'store-secret')) === 401);

    $c = freshWebhookSetup();
    CurlClient::$routes = [invoiceRoute('test_123', 400, ['message' => 'not found'])];
    $test = json_encode(['type' => 'Settled', 'invoiceId' => 'test_123']);
    check('webhook: dashboard test webhook (unknown invoice) -> 200', $c->send($test, coinsnapHeader($test, 'store-secret')) === 200);
    check('webhook: test webhook leaves an INFO line, not SECURITY', preg_grep('/INFO webhook reason=unknown invoice invoice=test_123/', $c->log) !== [] && preg_grep('/SECURITY/', $c->log) === []);
    check('webhook: test webhook changes nothing', $c->statusUpdates === []);

    $c = freshWebhookSetup();
    CurlClient::$routes = [['GET', '#invoices#', 'down', '']];
    check('webhook: API unreachable -> 503', $c->send($body, coinsnapHeader($body, 'store-secret')) === 503);
    CurlClient::$routes = [invoiceRoute('inv_1', 502, [])];
    check('webhook: API 5xx -> 503', $c->send($body, coinsnapHeader($body, 'store-secret')) === 503);
    check('webhook: no store ID or key in the log', preg_grep('/CS-STORE-1|cs-key-1234|store-secret/', $c->log) === []);

    $c = freshWebhookSetup();
    CurlClient::$routes = [invoiceRoute('inv_1', 200, ['status' => 'New'] + $paid)];
    check('webhook: forged Settled (API says New) -> 200', $c->send($body, coinsnapHeader($body, 'store-secret')) === 200);
    check('webhook: forged Settled leaves the order unpaid and logs "forged status"', $c->statusUpdates === [] && preg_grep('/SECURITY webhook reason=forged status/', $c->log) !== []);

    $c = freshWebhookSetup();
    CurlClient::$routes = [invoiceRoute('inv_1', 200, ['invoiceAmount' => '10.00'] + $paid)];
    $c->send($body, coinsnapHeader($body, 'store-secret'));
    check('webhook: underpaid invoice leaves the order unpaid', $c->statusUpdates === []);

    $c = freshWebhookSetup();
    CurlClient::$routes = [invoiceRoute('inv_9', 200, ['id' => 'inv_9'] + $paid)];
    $other = json_encode(['type' => 'Settled', 'invoiceId' => 'inv_9']);
    check('webhook: settled invoice of another plugin -> 200, "not ours"', $c->send($other, coinsnapHeader($other, 'store-secret')) === 200 && preg_grep('/not ours/', $c->log) !== [] && $c->statusUpdates === []);

    $c = freshWebhookSetup();
    CurlClient::$routes = [invoiceRoute('inv_1', 200, $paid)];
    check('webhook: real payment -> 200', $c->send($body, coinsnapHeader($body, 'store-secret')) === 200);
    check('webhook: real payment settles the order', $c->statusUpdates === [[42, 'Settled']]);
    $c->send($body, coinsnapHeader($body, 'store-secret'));
    check('webhook: same webhook twice changes the order once', $c->statusUpdates === [[42, 'Settled']]);
    $exp = json_encode(['type' => 'Expired', 'invoiceId' => 'inv_1']);
    CurlClient::$routes = [invoiceRoute('inv_1', 200, ['status' => 'Expired'] + $paid)];
    $c->send($exp, coinsnapHeader($exp, 'store-secret'));
    check('webhook: Expired after paid changes nothing', $c->statusUpdates === [[42, 'Settled']]);

    $c = freshWebhookSetup();
    $c->orderStatus[42] = 99;                                   // merchant set "Cancelled" by hand
    CurlClient::$routes = [invoiceRoute('inv_1', 200, ['status' => 'Expired'] + $paid)];
    $c->send($exp, coinsnapHeader($exp, 'store-secret'));
    check('webhook: Expired keeps a status the merchant set by hand', $c->statusUpdates === [] && $c->orderStatus[42] === 99);

    $c = freshWebhookSetup();
    $c->failOrderUpdate = true;
    CurlClient::$routes = [invoiceRoute('inv_1', 200, $paid)];
    check('webhook: order update fails -> 503 (provider retries)', $c->send($body, coinsnapHeader($body, 'store-secret')) === 503);
    check('webhook: failed update does not mark the invoice processed', $c->records->rows['inv_1']['paid_at'] === null);
    $c->failOrderUpdate = false;
    check('webhook: the retry settles the order', $c->send($body, coinsnapHeader($body, 'store-secret')) === 200 && $c->statusUpdates === [[42, 'Settled']]);

    // Orders from before 1.2.0 have a transaction row but no invoice record.
    $c = freshWebhookSetup();
    $c->records->rows = [];
    $c->tx->rows['inv_1']['created_at'] = date('Y-m-d H:i:s', time() - 3600);
    CurlClient::$routes = [invoiceRoute('inv_1', 200, $paid)];
    $c->send($body, coinsnapHeader($body, 'store-secret'));
    check('webhook: order from before the upgrade, placed 1 h ago, is settled', $c->statusUpdates === [[42, 'Settled']]);
    $c = freshWebhookSetup();
    $c->records->rows = [];
    $c->tx->rows['inv_1']['created_at'] = date('Y-m-d H:i:s', time() - 2 * 86400);
    CurlClient::$routes = [invoiceRoute('inv_1', 200, $paid)];
    $c->send($body, coinsnapHeader($body, 'store-secret'));
    check('webhook: order without record older than one day is not settled', $c->statusUpdates === [] && preg_grep('/no invoice record/', $c->log) !== []);

    // Pre-1.2.0 secret keeps working until the merchant saves the settings.
    $c = freshWebhookSetup();
    ConfigurationStorage::$data = array_filter(ConfigurationStorage::$data, fn ($k) => strpos($k, 'webhook_') !== 0, ARRAY_FILTER_USE_KEY);
    ConfigurationStorage::$data['webhook'] = json_encode(['id' => 'old', 'secret' => 'legacy-secret', 'url' => 'u']);
    CurlClient::$routes = [invoiceRoute('inv_1', 200, $paid)];
    check('webhook: pre-1.2.0 secret accepted until settings are saved', $c->send($body, coinsnapHeader($body, 'legacy-secret')) === 200 && $c->statusUpdates === [[42, 'Settled']]);

    $c = freshWebhookSetup('btcpay');
    $bp = json_encode(['type' => 'InvoiceSettled', 'invoiceId' => 'inv_1']);
    CurlClient::$routes = [invoiceRoute('inv_1', 200, ['status' => 'Settled', 'amount' => '100.00', 'currency' => 'EUR'])];
    check('webhook: BTCPay signature with the BTCPay store secret settles', $c->send($bp, btcpayHeader($bp, 'store-secret')) === 200 && $c->statusUpdates === [[42, 'Settled']]);
    check('webhook: BTCPay invoice fetched from the saved BTCPay URL with its key', strpos(CurlClient::$calls[0][1], 'https://btcpay.example/api/v1/stores/BTC-STORE-1/invoices/inv_1') === 0
        && CurlClient::$calls[0][2]['Authorization'] === 'token btc-key-5678');
    $c = freshWebhookSetup('btcpay');
    CurlClient::$routes = [invoiceRoute('inv_1', 404, [])];
    check('webhook: BTCPay unknown invoice (404) -> 200', $c->send($bp, btcpayHeader($bp, 'store-secret')) === 200);

    // ===== Admin settings =====
    function adminSetup(array $stored): CoinsnapModuleCenterModuleController
    {
        ConfigurationStorage::$data = $stored;
        $GLOBALS['messageStack'] = new MessageStack();
        $_SESSION = [];
        AbstractModuleCenterModuleController::$tokenValid = true;
        CurlClient::$calls = [];
        CurlClient::$routes = [
            ['GET', '#/api/v1/stores/[^/]+$#', 200, '{"id":"S"}'],
            ['GET', '#/payment-methods$#', 200, '[{"enabled":true,"paymentMethodId":"BTC-LN"}]'],
            ['GET', '#/webhooks$#', 200, '[{"id":"stale","url":"https://shop.example/shop.php?do=CoinsnapWebhook/Index"}]'],
            ['DELETE', '#/webhooks/stale$#', 200, ''],
            ['POST', '#/webhooks$#', 200, '{"id":"new-wh","secret":"fresh-secret","url":"https://shop.example/shop.php?do=CoinsnapWebhook/Index"}'],
            ['GET', '#rates|currencies#', 200, '[]'],
        ];
        return new CoinsnapModuleCenterModuleController();
    }
    function save(CoinsnapModuleCenterModuleController $c, array $form): ?string
    {
        AbstractModuleCenterModuleController::$post = ['configuration' => $form];
        try {
            $c->actionSaveConfiguration();
        } catch (PageTokenRefused $e) {
            return 'refused';
        } catch (Throwable $e) {
            // The rest of the save handler (payment-method sync) needs a real shop; what matters ran before it.
            return get_class($e) . ': ' . $e->getMessage();
        }
        return null;
    }
    $baseForm = ['provider' => 'coinsnap', 'storeid' => 'CS-STORE-1', 'apikey' => '', 'btcpay_serverurl' => 'https://btcpay.example', 'btcpay_storeid' => 'BTC-STORE-1', 'btcpay_apikey' => ''];
    $stored = ['provider' => 'coinsnap', 'storeid' => 'CS-STORE-1', 'apikey' => 'cs-key-1234', 'btcpay_serverurl' => 'https://btcpay.example',
        'btcpay_storeid' => 'BTC-STORE-1', 'btcpay_apikey' => 'btc-key-5678', 'discount_enabled' => '1', 'autoredirect' => '1',
        'webhook' => json_encode(['id' => 'old', 'secret' => 'legacy-secret', 'url' => 'https://shop.example/shop.php?do=CoinsnapWebhook/Index'])];

    $c = adminSetup($stored);
    AbstractModuleCenterModuleController::$tokenValid = false;
    check('admin: save without a valid page token is refused', save($c, ['apikey' => 'attacker-key'] + $baseForm) === 'refused');
    check('admin: ...and makes no API call and changes nothing', CurlClient::$calls === [] && ConfigurationStorage::$data === $stored);

    $c = adminSetup($stored);
    $view = (new ReflectionMethod($c, 'configurationForView'))->invoke($c);
    check('admin: settings page gets no webhook secret and no API key', strpos(json_encode($view), 'legacy-secret') === false && strpos(json_encode($view), 'cs-key-1234') === false
        && $view['apikey_hint'] === '••••••••1234');

    $c = adminSetup($stored);
    save($c, $baseForm + ['webhook_coinsnap_x' => 'injected']);
    $st = new CoinsnapStorage();
    check('admin: empty key field keeps the stored Coinsnap key', $st->get('apikey') === 'cs-key-1234');
    check('admin: unticked checkboxes turn discount and redirect off', $st->get('discount_enabled') === '0' && $st->get('autoredirect') === '0');
    check('admin: posted fields outside the form are ignored', !array_key_exists('webhook_coinsnap_x', ConfigurationStorage::$data));
    check('admin: save without a per-store record replaces the webhook (new secret)', ($st->getWebhook('coinsnap', 'CS-STORE-1')['secret'] ?? '') === 'fresh-secret');
    check('admin: the old shop webhook is deleted on the server', in_array(['DELETE', 'stale'], array_map(fn ($x) => [$x[0], basename($x[1])], CurlClient::$calls), true));
    check('admin: old shared secret no longer accepted', $st->getWebhook('coinsnap', 'CS-STORE-1', true)['secret'] === 'fresh-secret' && json_decode((string) $st->get('webhook'), true) === null);
    $created = array_values(array_filter(CurlClient::$calls, fn ($x) => $x[0] === 'POST' && preg_match('#/webhooks$#', $x[1])));
    check('admin: webhook URL comes from the shop configuration', count($created) === 1 && (json_decode($created[0][3], true)['url'] ?? '') === 'https://shop.example/shop.php?do=CoinsnapWebhook/Index');

    $c = adminSetup($stored);
    $_SERVER['HTTP_HOST'] = 'evil.example';
    save($c, $baseForm);
    $hosts = array_map(fn ($x) => parse_url($x[1], PHP_URL_HOST), CurlClient::$calls);
    $created = array_values(array_filter(CurlClient::$calls, fn ($x) => $x[0] === 'POST'));
    check('admin: a forged Host header does not reach any API call or the webhook URL', !in_array('evil.example', $hosts, true) && $created !== [] && strpos($created[0][3], 'evil.example') === false);
    unset($_SERVER['HTTP_HOST']);

    $c = adminSetup($stored + []);
    save($c, ['provider' => 'btcpay', 'btcpay_serverurl' => 'https://other-btcpay.example'] + $baseForm);
    $st = new CoinsnapStorage();
    check('admin (6.14): new BTCPay URL with empty key field removes the stored key', $st->get('btcpay_apikey') === '' && $st->get('btcpay_serverurl') === 'https://other-btcpay.example');
    check('admin (6.14): the old key is never sent to the new URL', array_filter(CurlClient::$calls, fn ($x) => strpos(json_encode($x[2]), 'btc-key-5678') !== false) === []);

    $c = adminSetup($stored);
    array_unshift(CurlClient::$routes, ['GET', '#api-keys/current$#', 200, '{"permissions":["unrestricted"]}']);
    save($c, ['provider' => 'btcpay', 'btcpay_apikey' => 'unrestricted-key'] + $baseForm);
    $st = new CoinsnapStorage();
    check('admin: pasted unrestricted BTCPay key is refused', $st->get('btcpay_apikey') === 'btc-key-5678'
        && in_array(['error', 'coinsnap_btcpay_key_refused'], $GLOBALS['messageStack']->messages, true));

    $c = adminSetup($stored);
    array_unshift(CurlClient::$routes, ['GET', '#api-keys/current$#', 200, '{"permissions":["btcpay.store.cancreateinvoice:OTHER-STORE"]}']);
    save($c, ['provider' => 'btcpay', 'btcpay_apikey' => 'other-store-key'] + $baseForm);
    check('admin: BTCPay key for another store is refused', (new CoinsnapStorage())->get('btcpay_apikey') === 'btc-key-5678');

    $c = adminSetup($stored);
    array_unshift(CurlClient::$routes, ['GET', '#api-keys/current$#', 200, '{"permissions":["btcpay.store.cancreateinvoice:BTC-STORE-1","btcpay.store.canviewinvoices:BTC-STORE-1","btcpay.store.webhooks.canmodifywebhooks:BTC-STORE-1"]}']);
    save($c, ['provider' => 'btcpay', 'btcpay_apikey' => 'good-key'] + $baseForm);
    $st = new CoinsnapStorage();
    check('admin: BTCPay key limited to the store is saved', $st->get('btcpay_apikey') === 'good-key');
    check('admin: BTCPay webhook stored under the BTCPay store\'s own key', ($st->getWebhook('btcpay', 'BTC-STORE-1')['secret'] ?? '') === 'fresh-secret');

    $c = adminSetup($stored);
    array_unshift(CurlClient::$routes, ['GET', '#api-keys/current$#', 200, '{"permissions":["unrestricted"]}']);
    save($c, ['provider' => 'btcpay'] + $baseForm);
    check('admin: stored pre-1.2.0 unrestricted key is kept but flagged', (new CoinsnapStorage())->get('btcpay_apikey') === 'btc-key-5678'
        && in_array(['error', 'coinsnap_btcpay_key_unrestricted'], $GLOBALS['messageStack']->messages, true));

    $c = adminSetup($stored);
    save($c, ['provider' => 'btcpay', 'btcpay_serverurl' => 'file:///etc/passwd', 'btcpay_apikey' => 'k'] + $baseForm);
    check('admin: non-http BTCPay URL is not saved', (new CoinsnapStorage())->get('btcpay_serverurl') === '');

    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
