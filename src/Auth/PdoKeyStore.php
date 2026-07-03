<?php

declare(strict_types=1);

namespace Introibo\Api\Auth;

use PDO;

/**
 * The MySQL-backed {@see KeyStore} (#22), matching `sql/schema.sql`. Counters use an
 * atomic upsert (`INSERT … ON DUPLICATE KEY UPDATE count = count + 1`) so no row is
 * ever written per request; the read-back returns the running total.
 *
 * This is exercised against a live MySQL at deploy time (a maintainer step) — CI has
 * no database — so the auth/quota/rate logic is proven against {@see InMemoryKeyStore}
 * and this class mirrors its semantics exactly.
 */
final class PdoKeyStore implements KeyStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findKeyByHash(string $hash): ?ApiKey
    {
        return $this->fetchKey('hash', $hash);
    }

    public function findKey(string $keyId): ?ApiKey
    {
        return $this->fetchKey('id', $keyId);
    }

    public function findTenant(string $tenantId): ?Tenant
    {
        $statement = $this->pdo->prepare('SELECT id, name, monthly_quota FROM tenants WHERE id = ?');
        $statement->execute([$tenantId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return new Tenant(
            (string) $row['id'],
            (string) $row['name'],
            $row['monthly_quota'] === null ? null : (int) $row['monthly_quota'],
        );
    }

    public function putTenant(Tenant $tenant): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tenants (id, name, monthly_quota) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE name = VALUES(name), monthly_quota = VALUES(monthly_quota)',
        );
        $statement->execute([$tenant->id, $tenant->name, $tenant->monthlyQuota]);
    }

    public function putKey(ApiKey $key): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO api_keys (id, tenant_id, hash, active, rate_per_minute, label) VALUES (?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE active = VALUES(active), '
            . 'rate_per_minute = VALUES(rate_per_minute), label = VALUES(label)',
        );
        $statement->execute([
            $key->id,
            $key->tenantId,
            $key->hash,
            $key->active ? 1 : 0,
            $key->ratePerMinute,
            $key->label,
        ]);
    }

    public function bumpUsage(string $tenantId, string $period): int
    {
        $this->pdo->prepare(
            'INSERT INTO usage_counters (tenant_id, period, count) VALUES (?, ?, 1) '
            . 'ON DUPLICATE KEY UPDATE count = count + 1',
        )->execute([$tenantId, $period]);

        $statement = $this->pdo->prepare('SELECT count FROM usage_counters WHERE tenant_id = ? AND period = ?');
        $statement->execute([$tenantId, $period]);

        return (int) $statement->fetchColumn();
    }

    public function bumpRate(string $keyId, string $window): int
    {
        $this->pdo->prepare(
            'INSERT INTO rate_windows (key_id, window_start, count) VALUES (?, ?, 1) '
            . 'ON DUPLICATE KEY UPDATE count = count + 1',
        )->execute([$keyId, $window]);

        $statement = $this->pdo->prepare('SELECT count FROM rate_windows WHERE key_id = ? AND window_start = ?');
        $statement->execute([$keyId, $window]);

        return (int) $statement->fetchColumn();
    }

    private function fetchKey(string $column, string $value): ?ApiKey
    {
        $statement = $this->pdo->prepare(
            "SELECT id, tenant_id, hash, active, rate_per_minute, label FROM api_keys WHERE {$column} = ?",
        );
        $statement->execute([$value]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return new ApiKey(
            (string) $row['id'],
            (string) $row['tenant_id'],
            (string) $row['hash'],
            (bool) $row['active'],
            $row['rate_per_minute'] === null ? null : (int) $row['rate_per_minute'],
            (string) $row['label'],
        );
    }
}
