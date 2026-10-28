<?php

declare(strict_types=1);

namespace Reservations\Core\Support;

use InvalidArgumentException;

/**
 * Colour helpers for status colours. Uses the WCAG relative luminance so the
 * chosen text colour actually meets contrast requirements (news_img uses YIQ,
 * which is close but not WCAG-based).
 */
final class Color
{
    public static function isValidHex(string $color): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color);
    }

    /**
     * Black or white, whichever contrasts more with the background.
     */
    public static function contrastText(string $background): string
    {
        return self::contrastRatio($background, '#000000') >= self::contrastRatio($background, '#ffffff')
            ? '#000000'
            : '#ffffff';
    }

    public static function contrastRatio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function luminance(string $color): float
    {
        if (!self::isValidHex($color)) {
            throw new InvalidArgumentException(sprintf('Invalid colour "%s".', $color));
        }
        $channels = array_map(
            static function (string $hex): float {
                $c = hexdec($hex) / 255;
                return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($color, 1), 2),
        );
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
