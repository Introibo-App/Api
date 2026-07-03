<?php

declare(strict_types=1);

namespace Introibo\Api\Tests\Contract;

use Introibo\Api\Contract\Envelope;
use PHPUnit\Framework\TestCase;

final class EnvelopeTest extends TestCase
{
    public function testWrapsDataAndMeta(): void
    {
        $envelope = Envelope::of(['a' => 1], Envelope::meta('c1+e1+d1', ['date' => '2026-09-15']));

        self::assertSame(['a' => 1], $envelope['data']);
        self::assertSame('c1+e1+d1', $envelope['meta']['dataVersion']);
        self::assertSame(['date' => '2026-09-15'], $envelope['meta']['request']);
    }

    public function testMetaOmitsRequestWhenEmpty(): void
    {
        $meta = Envelope::meta('v');

        self::assertSame(['dataVersion' => 'v'], $meta);
        self::assertArrayNotHasKey('request', $meta);
    }
}
