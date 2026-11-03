<?php

declare(strict_types=1);

namespace Reservations\Core\Support;

/**
 * Where to send a visitor back after a form post. The form tells its own path;
 * only paths below the site are accepted, and the host always comes from the
 * site URL, so the redirect can never lead to another site (open redirect).
 */
final class ReturnUrl
{
    /**
     * @param string $siteUrl  e.g. "https://example.com/cms"
     * @param string $path     path from the form, e.g. "/cms/pages/rooms.php?month=2"
     * @param string $fragment anchor without "#"
     */
    public static function resolve(string $siteUrl, string $path, string $fragment = ''): string
    {
        $base = rtrim((string) parse_url($siteUrl, PHP_URL_PATH), '/');
        $path = (string) strtok($path, '#');
        // "//host" is a host for browsers, backslashes and control characters are read as "/" or break the header
        if (!str_starts_with($path, $base . '/') || str_starts_with($path, '//') || preg_match('/[\x00-\x1f\x7f\\\\]/', $path) === 1) {
            $path = $base . '/';
        }
        $origin = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $siteUrl);
        return $origin . $path . ($fragment === '' ? '' : '#' . $fragment);
    }
}
