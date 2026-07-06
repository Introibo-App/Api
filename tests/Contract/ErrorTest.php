<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Contract;

use Directorium\Api\Contract\ApiError;
use Directorium\Api\Contract\ErrorCode;
use Directorium\Api\Contract\Json;
use PHPUnit\Framework\TestCase;

final class ErrorTest extends TestCase
{
    public function testApiErrorSerialisesToTheCanonicalShape(): void
    {
        $error = ApiError::of(ErrorCode::UNKNOWN_CALENDAR, 'The calendar "bogus" is not known.');

        self::assertSame(
            ['error' => [
                'code' => 'unknown_calendar',
                'message' => 'The calendar "bogus" is not known.',
                'status' => 422,
            ]],
            $error->payload(),
        );
    }

    public function testDefaultMessageIsUsedWhenNoneGiven(): void
    {
        $error = ApiError::of(ErrorCode::NOT_FOUND);

        self::assertSame(404, $error->status);
        self::assertSame(ErrorCode::NOT_FOUND->defaultMessage(), $error->message);
    }

    public function testInternalErrorIs500(): void
    {
        self::assertSame(500, ApiError::internal()->status);
        self::assertSame('internal_error', ApiError::internal()->code);
    }

    public function testEveryCodeHasAClientStatusAndAMessage(): void
    {
        foreach (ErrorCode::cases() as $code) {
            self::assertGreaterThanOrEqual(400, $code->status());
            self::assertLessThan(600, $code->status());
            self::assertNotSame('', $code->defaultMessage());
        }
    }

    public function testJsonUsesTheFrozenFlags(): void
    {
        $payload = ['id' => 'roman:a/b', 'name' => "Andr\u{e9}"];
        $expected = '{"id":"roman:a/b","name":"André"}';

        // Unicode stays legible and slashes are not escaped — same as Core's contract.
        self::assertSame($expected, Json::encode($payload));
    }
}
