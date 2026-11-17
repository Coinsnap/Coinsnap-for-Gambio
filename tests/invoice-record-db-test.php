<?php
// InvoiceRecord against a real MySQL/MariaDB, through xtc_db_* shims over mysqli.
// Run: DB_HOST=... DB_USER=... DB_PASS=... DB_NAME=... php tests/invoice-record-db-test.php
declare(strict_types=1);

$db = new mysqli(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', getenv('DB_NAME') ?: 'gambio');
$db->set_charset('utf8mb4');
function xtc_db_query($q) { global $db; $r = $db->query($q); if ($r === false) { throw new RuntimeException($db->error); } return $r; }
function xtc_db_fetch_array($r) { return $r instanceof mysqli_result ? ($r->fetch_assoc() ?: false) : false; }
function xtc_db_input($v) { global $db; return $db->real_escape_string((string) $v); }

require dirname(__DIR__) . '/GXModules/Coinsnap/CoinsnapPayment/Library/Webhook/InvoiceRecord.php';
use GXModules\Library\Webhook\InvoiceRecord;

$passed = 0;
$failed = 0;
function check(string $name, bool $ok): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
}

xtc_db_query('DROP TABLE IF EXISTS `coinsnap_invoice_expected`');
$r = new InvoiceRecord();
check('table is created on first use', xtc_db_fetch_array(xtc_db_query("SHOW TABLES LIKE 'coinsnap_invoice_expected'")) !== false);
$r2 = new InvoiceRecord();
check('second construction is harmless', true);

$r->remember('inv_a', 7, '100.00', 'EUR');
$r->remember('inv_b', 7, '99.50', 'EUR');      // a second invoice for the same order
$r->remember('inv_a', 7, '0.01', 'EUR');       // a later remember never overwrites the recorded amount
check('every invoice of an order is kept', $r->find('inv_a', 7) === ['amount' => '100.00', 'currency' => 'EUR'] && $r->find('inv_b', 7) === ['amount' => '99.50', 'currency' => 'EUR']);
check('an invoice is found only for its own order', $r->find('inv_a', 8) === null);
check("quotes in the invoice id are harmless", $r->find("x' OR '1'='1", 7) === null);
check('order not paid yet', !$r->orderIsPaid(7));

$token = $r->claimPaid('inv_a', 7, '100.00', 'EUR');
check('first claim wins', is_string($token) && strlen($token) === 32);
check('second claim for the same invoice loses', $r2->claimPaid('inv_a', 7, '100.00', 'EUR') === null);
check('order is paid', $r->orderIsPaid(7));
$r->releaseClaim('inv_a', 'not-the-token');
check('release with a wrong token does nothing', $r->orderIsPaid(7));
$r->releaseClaim('inv_a', $token);
check('release with the claim token undoes the claim', !$r->orderIsPaid(7));
$again = $r2->claimPaid('inv_a', 7, '100.00', 'EUR');
check('after release the retry can claim', is_string($again));
check('claim of an unrecorded invoice records it (legacy orders)', is_string($r->claimPaid('legacy_1', 9, '20.00', 'USD')) && $r->find('legacy_1', 9) === ['amount' => '20.00', 'currency' => 'USD']);

// Many concurrent claimers: exactly one wins (needs the pcntl extension).
if (function_exists('pcntl_fork')) {
    $r->remember('race', 11, '5.00', 'EUR');
    $pids = [];
    for ($i = 0; $i < 8; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            $db = new mysqli(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', getenv('DB_NAME') ?: 'gambio');
            exit((new InvoiceRecord())->claimPaid('race', 11, '5.00', 'EUR') !== null ? 0 : 1);
        }
        $pids[] = $pid;
    }
    $winners = 0;
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $winners += pcntl_wexitstatus($status) === 0 ? 1 : 0;
    }
    check('8 concurrent webhooks: exactly one claims the invoice', $winners === 1);
} else {
    echo "SKIP concurrency check (no pcntl)\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
