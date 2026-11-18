<?php
declare(strict_types=1);

namespace GXModules\Library\Btcpay;

use Coinsnap\Http\ClientInterface;
use Coinsnap\Http\CurlClient;

// What a BTCPay API key may do, asked from BTCPay itself (never taken from a form or callback).
class KeyPermissions
{
    // The key's permissions, or null when BTCPay can't be asked or refuses the key.
    public static function fetch(string $serverUrl, string $apiKey, ?ClientInterface $http = null): ?array
    {
        if ($apiKey === '' || !self::isServerUrl($serverUrl)) {
            return null;
        }
        try {
            $response = ($http ?? new CurlClient())->request(
                'GET',
                rtrim($serverUrl, '/') . '/api/v1/api-keys/current',
                ['Authorization' => 'token ' . $apiKey, 'Accept' => 'application/json']
            );
        } catch (\Throwable $e) {
            return null;
        }
        if ($response->getStatus() !== 200) {
            return null;
        }
        $key = json_decode($response->getBody(), true);
        return is_array($key) && is_array($key['permissions'] ?? null) ? array_values($key['permissions']) : null;
    }

    // Only store permissions, each limited to this one store. A permission without a store ID works for every store;
    // "unrestricted" and btcpay.server.* / btcpay.user.* control the whole account.
    public static function acceptable(array $permissions, string $storeId): bool
    {
        if ($permissions === [] || $storeId === '') {
            return false;
        }
        foreach ($permissions as $permission) {
            [$policy, $scope] = array_pad(explode(':', (string) $permission, 2), 2, '');
            if (strpos($policy, 'btcpay.store.') !== 0 || $scope !== $storeId) {
                return false;
            }
        }
        return true;
    }

    // http(s) only, so a typed URL can never make cURL read a local file or another protocol.
    public static function isServerUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) && (string) parse_url($url, PHP_URL_HOST) !== '';
    }
}
