<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Support;

use PHPUnit\Framework\TestCase;
use Reservations\Core\Support\ReturnUrl;

final class ReturnUrlTest extends TestCase
{
    public function testPathsBelowTheSiteAreKept(): void
    {
        self::assertSame(
            'http://localhost:4443/wbce/pages/rooms.php?rsv_month_2=2026-11#rsv-request-2',
            ReturnUrl::resolve('http://localhost:4443/wbce', '/wbce/pages/rooms.php?rsv_month_2=2026-11#old', 'rsv-request-2'),
        );
        self::assertSame('https://example.com/rooms.php', ReturnUrl::resolve('https://example.com/', '/rooms.php'));
    }

    public function testEverythingElseGoesToTheStartPage(): void
    {
        foreach (['//evil.example/x', 'https://evil.example/', '/other/', '', 'pages/rooms.php', "/wbce/x\r\nSet-Cookie: a=b", '/wbce/\\evil.example', '/wbce'] as $path) {
            self::assertSame('http://localhost:4443/wbce/#a', ReturnUrl::resolve('http://localhost:4443/wbce', $path, 'a'), $path);
        }
        // at the root of the domain, "//" must still not pass as a path
        self::assertSame('https://example.com/', ReturnUrl::resolve('https://example.com', '//evil.example/'));
    }
}
