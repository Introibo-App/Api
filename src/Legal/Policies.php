<?php

declare(strict_types=1);

namespace Directorium\Api\Legal;

/**
 * The service's published policies (#30). Kept as data so the AUP and terms are
 * served through the same contract as everything else and versioned explicitly.
 */
final class Policies
{
    private const VERSION = '2026-07-03';

    public static function aup(): Policy
    {
        return new Policy('aup', 'Acceptable Use Policy', self::VERSION, self::AUP);
    }

    public static function terms(): Policy
    {
        return new Policy('terms', 'Terms of Use', self::VERSION, self::TERMS);
    }

    private const AUP = <<<'MD'
# Acceptable Use Policy

The Directorium API serves the traditional Roman liturgical calendar. By using it you agree:

- **Use an API key** where one is required, and do not share, resell, or pool keys to
  evade quotas or rate limits.
- **Respect the limits.** Do not attempt to circumvent rate limiting or quotas. If you
  need a higher allowance, request one.
- **Cache responsibly.** Responses are immutable per data version and carry cache
  headers — cache them rather than re-requesting identical data in a tight loop.
- **Bulk data belongs in the dataset.** The full calendar is published as a CC0 dataset;
  use that for bulk or offline needs instead of scraping the API day by day.
- **No unlawful, abusive, or disruptive use**, and no attempt to probe, overload, or
  compromise the service or other tenants.

Abuse may result in throttling or key revocation.
MD;

    private const TERMS = <<<'MD'
# Terms of Use

- **No warranty.** The service and its data are provided "as is", without warranty of
  any kind. While accuracy is the project's central goal, correctness is not guaranteed;
  verify anything liturgically consequential against the published rubrics and sources.
- **Licensing.** The software is licensed under **AGPL-3.0-or-later**; the compiled
  liturgical dataset is released under **CC0** (public domain). Attribution is
  appreciated but not required for the data.
- **Availability & change.** Endpoints, limits, and these terms may change. Breaking
  changes to the response contract are made under a new API version; `/v1` remains
  additive-only.
- **Liability.** To the extent permitted by law, the maintainers are not liable for any
  loss arising from use of the service.

Questions and liturgical corrections are welcome via the project's issue tracker.
MD;
}
