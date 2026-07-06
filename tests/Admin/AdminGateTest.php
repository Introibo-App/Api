<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Admin;

use Directorium\Api\Admin\AdminGate;
use Directorium\Api\Contract\ApiException;
use Directorium\Api\Http\Request;
use PHPUnit\Framework\TestCase;

final class AdminGateTest extends TestCase
{
    public function testAuthorisesAMatchingTokenByBearerOrHeader(): void
    {
        $gate = new AdminGate('s3cret');

        $gate->authorise(new Request('POST', '/', [], ['authorization' => 'Bearer s3cret']));
        $gate->authorise(new Request('POST', '/', [], ['x-admin-token' => 's3cret']));

        self::assertTrue($gate->enabled());
    }

    public function testRejectsAMissingOrWrongToken(): void
    {
        $gate = new AdminGate('s3cret');

        foreach ([[], ['x-admin-token' => 'nope'], ['authorization' => 'Bearer nope']] as $headers) {
            try {
                $gate->authorise(new Request('POST', '/', [], $headers));
                self::fail('Expected an unauthenticated ApiException.');
            } catch (ApiException $e) {
                self::assertSame('unauthenticated', $e->error()->code);
                self::assertSame(401, $e->error()->status);
            }
        }
    }

    public function testDisabledGateReportsDisabledAndRejectsEverything(): void
    {
        $gate = new AdminGate(null);
        self::assertFalse($gate->enabled());

        $this->expectException(ApiException::class);
        $gate->authorise(new Request('POST', '/', [], ['x-admin-token' => 'anything']));
    }
}
