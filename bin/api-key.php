<?php

/**
 * Manage tenants and API keys (#23). A maintainer tool: it talks straight to the
 * access-control database (INTROIBO_DB_DSN), so it runs where that database lives.
 *
 * Usage:
 *   php bin/api-key.php tenant <id> <name> [monthlyQuota]
 *   php bin/api-key.php issue  <tenantId> [label] [ratePerMinute]
 *   php bin/api-key.php rotate <keyId>
 *   php bin/api-key.php revoke <keyId>
 *
 * `issue` and `rotate` print the plaintext secret exactly once — it is never stored.
 */

declare(strict_types=1);

use Directorium\Api\Auth\KeyIssuer;
use Directorium\Api\Auth\PdoKeyStore;
use Directorium\Api\Auth\Tenant;

require dirname(__DIR__) . '/vendor/autoload.php';

$dsn = getenv('INTROIBO_DB_DSN');
if ($dsn === false || $dsn === '') {
    fwrite(STDERR, "error: set INTROIBO_DB_DSN (and _USER / _PASSWORD) to the API database.\n");
    exit(2);
}

$user = getenv('INTROIBO_DB_USER');
$password = getenv('INTROIBO_DB_PASSWORD');
$pdo = new PDO(
    $dsn,
    $user === false ? null : $user,
    $password === false ? null : $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$store = new PdoKeyStore($pdo);
$issuer = new KeyIssuer($store);

$command = $argv[1] ?? '';
$secretNote = "  (store the secret now — it is never shown again)\n";

switch ($command) {
    case 'tenant':
        $id = $argv[2] ?? '';
        $name = $argv[3] ?? '';
        if ($id === '' || $name === '') {
            fwrite(STDERR, "usage: php bin/api-key.php tenant <id> <name> [monthlyQuota]\n");
            exit(2);
        }
        $store->putTenant(new Tenant($id, $name, isset($argv[4]) ? (int) $argv[4] : null));
        fwrite(STDOUT, sprintf("tenant %s saved\n", $id));
        break;

    case 'issue':
        $tenantId = $argv[2] ?? '';
        if ($tenantId === '') {
            fwrite(STDERR, "usage: php bin/api-key.php issue <tenantId> [label] [ratePerMinute]\n");
            exit(2);
        }
        $issued = $issuer->issue($tenantId, $argv[3] ?? '', isset($argv[4]) ? (int) $argv[4] : null);
        fwrite(STDOUT, sprintf("key id: %s\nsecret: %s\n%s", $issued->key->id, $issued->secret, $secretNote));
        break;

    case 'rotate':
        $keyId = $argv[2] ?? '';
        if ($keyId === '') {
            fwrite(STDERR, "usage: php bin/api-key.php rotate <keyId>\n");
            exit(2);
        }
        $issued = $issuer->rotate($keyId);
        fwrite(STDOUT, sprintf(
            "rotated; new key id: %s\nsecret: %s\n%s",
            $issued->key->id,
            $issued->secret,
            $secretNote,
        ));
        break;

    case 'revoke':
        $keyId = $argv[2] ?? '';
        if ($keyId === '') {
            fwrite(STDERR, "usage: php bin/api-key.php revoke <keyId>\n");
            exit(2);
        }
        $issuer->revoke($keyId);
        fwrite(STDOUT, sprintf("key %s revoked\n", $keyId));
        break;

    default:
        fwrite(STDERR, "usage: php bin/api-key.php {tenant|issue|rotate|revoke} …\n");
        exit(2);
}
