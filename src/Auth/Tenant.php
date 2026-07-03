<?php

declare(strict_types=1);

namespace Introibo\Api\Auth;

/**
 * An API consumer account. Requests authenticate to a tenant (via one of its keys)
 * and the tenant's monthly request quota is metered against an aggregate counter.
 */
final readonly class Tenant
{
    /**
     * @param int|null $monthlyQuota Maximum requests per calendar month, or null for unlimited.
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?int $monthlyQuota = null,
    ) {
    }
}
