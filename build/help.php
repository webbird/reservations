<?php

/**
 * Builds the user help: Markdown from docs/help/<lang>/ to static HTML in wbce/help/.
 * Usage: php build/help.php (build.php runs it too). wbce/help/ is generated, never edit it.
 * Errors (dead link, page missing in _nav.md) end with exit code 1; warnings (missing
 * screenshot, page in one language only) are printed and the build goes on.
 * Conventions for the Markdown files: docs/help/README.md
 */

declare(strict_types=1);

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

$root = dirname(__DIR__);
require $root . '/core/vendor/autoload.php';

$source = $root . '/docs/help';
$target = $root . '/wbce/help';
$errors = [];
$warnings = [];

$ui = [
    'de' => [
        'name' => 'Deutsch', 'site' => 'Hilfe zu Reservations', 'help' => 'Hilfe', 'skip' => 'Zum Inhalt springen',
        'version' => 'Version %s', 'language' => 'Sprache', 'topics' => 'Themen', 'path' => 'Pfad', 'on_page' => 'Auf dieser Seite',
        'applies' => 'Gilt für', 'since' => 'seit Version %s', 'turn' => 'Blättern', 'back' => 'Zurück', 'next' => 'Weiter',
        'note' => 'Hinweis', 'warning' => 'Achtung', 'missing' => 'Bildschirmfoto folgt: %s',
        'footer' => 'Reservations %s · für WBCE ab 1.7.0', 'offline' => 'Diese Hilfe liegt dem Modul bei und funktioniert ohne Internetverbindung.',
    ],
    'en' => [
        'name' => 'English', 'site' => 'Reservations help', 'help' => 'Help', 'skip' => 'Skip to content',
        'version' => 'Version %s', 'language' => 'Language', 'topics' => 'Topics', 'path' => 'Path', 'on_page' => 'On this page',
        'applies' => 'Applies to', 'since' => 'since version %s', 'turn' => 'Page through', 'back' => 'Back', 'next' => 'Next',
        'note' => 'Note', 'warning' => 'Warning', 'missing' => 'Screenshot to follow: %s',
        'footer' => 'Reservations %s · for WBCE 1.7.0 and later', 'offline' => 'This help ships with the module and works without an internet connection.',
    ],
];
$icons = [
    'note' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 11v6M12 7.5v.5"/></svg>',
    'warning' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3 2 21h20L12 3z"/><path d="M12 10v5M12 18v.5"/></svg>',
];

$info = (string) file_get_contents($root . '/wbce/info.php');
if (preg_match('/\$module_version\s*=\s*\'([^\']+)\'/', $info, $m) !== 1) {
    fwrite(STDERR, "No \$module_version in wbce/info.php\n");
    exit(1);
}
$version = $m[1];

$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$slug = static function (string $html): string {
    $text = mb_strtolower(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'])), '-');
};

// in a function of its own: the variables of the template must not reach the loops below
$render = static function (array $p) use ($e): string {
    ob_start();
    include __DIR__ . '/help-template.php';
    return (string) ob_get_clean();
};

$environment = new Environment(['html_input' => 'allow', 'allow_unsafe_links' => false]);
$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new TableExtension());
$converter = new MarkdownConverter($environment);

/*
 * First pass: read navigation and pages of every language, turn the Markdown
 * into HTML. Links are checked in the second pass, when all anchors are known.
 */
$languages = [];
foreach ((array) glob($source . '/*/_nav.md') as $navFile) {
    $lang = basename(dirname((string) $navFile));
    if (!isset($ui[$lang])) {
        $errors[] = "$lang: no interface texts in build/help.php";
        continue;
    }
    /** @var list<array{title: string, pages: list<array{slug: string, title: string}>}> $nav */
    $nav = [];
    foreach ((array) file((string) $navFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $group = array_pop($nav);
        if ($group !== null) {
            $nav[] = $group;
        }
        if (preg_match('/^\s+- \[(.+)\]\((.+)\.md\)$/', (string) $line, $m) === 1 && $group !== null) {
            $group['pages'][] = ['slug' => $m[2], 'title' => $m[1]];
            $nav[array_key_last($nav)] = $group;
        } elseif (preg_match('/^- (.+)$/', (string) $line, $m) === 1) {
            $nav[] = ['title' => $m[1], 'pages' => []];
        } else {
            $errors[] = "$lang/_nav.md: cannot read \"$line\"";
        }
    }

    $pages = [];
    foreach ((array) glob(dirname((string) $navFile) . '/*.md') as $file) {
        $name = basename((string) $file, '.md');
        if ($name === '_nav') {
            continue;
        }
        $text = (string) file_get_contents((string) $file);
        $meta = [];
        while (preg_match('/\A([a-z]+): *(.*)\R/', $text, $m) === 1) {
            $meta[$m[1]] = trim($m[2]);
            $text = substr($text, strlen($m[0]));
        }
        $html = $converter->convert($text)->getContent();
        if (preg_match('#\A\s*<h1>(.*?)</h1>\s*(?:<p>(.*?)</p>)?#s', $html, $m) !== 1) {
            $errors[] = "$lang/$name.md: must start with a # heading";
            continue;
        }
        $toc = [];
        $html = (string) preg_replace_callback('#<h2>(.*?)</h2>#s', static function (array $h) use (&$toc, $slug): string {
            $id = $slug($h[1]);
            $toc[$id] = $h[1];
            return '<h2 id="' . $id . '">' . $h[1] . '</h2>';
        }, substr($html, strlen($m[0])));
        $pages[$name] = ['title' => $m[1], 'lead' => $m[2] ?? '', 'body' => $html, 'toc' => $toc, 'meta' => $meta];
    }

    $order = [];
    foreach ($nav as $group) {
        foreach ($group['pages'] as $page) {
            if (!isset($pages[$page['slug']])) {
                $errors[] = "$lang/_nav.md: page {$page['slug']}.md does not exist";
            }
            $order[] = $page['slug'];
        }
    }
    foreach (array_diff(array_keys($pages), $order, ['index']) as $name) {
        $errors[] = "$lang/$name.md: missing in _nav.md";
    }
    if (!isset($pages['index'])) {
        $errors[] = "$lang: index.md is missing";
    }
    $languages[$lang] = ['nav' => $nav, 'pages' => $pages, 'order' => $order];
}
if ($languages === []) {
    $errors[] = "no language with _nav.md in $source";
}

/*
 * Second pass: finish each page and write it. The patterns match the output of
 * CommonMark, which only ever gets our own Markdown files.
 */
$removeDir = static function (string $dir) use (&$removeDir): void {
    foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry) {
        $path = $dir . '/' . $entry;
        is_dir($path) ? $removeDir($path) : unlink($path);
    }
    rmdir($dir);
};
if ($errors === []) {
    if (is_dir($target)) {
        $removeDir($target);
    }
    mkdir($target, 0777, true);
    copy($source . '/help.css', $target . '/help.css');
    // WBCE convention: no directory without an index; this one has nothing else to show
    file_put_contents($target . '/index.php', "<?php\nheader('Location: " . array_key_first($languages) . "/index.html');\nexit;\n");
}

foreach ($languages as $lang => $language) {
    $texts = $ui[$lang];
    $pages = $language['pages'];
    $order = $language['order'];
    $dir = $source . '/' . $lang;
    foreach ($pages as $name => $page) {
        $context = "$lang/$name.md";
        $body = $page['body'];

        $topics = '';
        foreach ($language['nav'] as $group) {
            $links = '';
            foreach ($group['pages'] as $entry) {
                $links .= '<li><a href="' . $e($entry['slug']) . '.html">' . $e($entry['title']) . '</a></li>';
            }
            $topics .= '<div class="topic"><h3>' . $e($group['title']) . '</h3><ul>' . $links . '</ul></div>';
        }
        $body = str_replace('<p>{{topics}}</p>', '<div class="topics">' . $topics . '</div>', $body);

        $body = (string) preg_replace_callback('#href="([^":\#]+)\.md(?:\#([^"]*))?"#', static function (array $m) use ($pages, &$errors, $context): string {
            $anchor = $m[2] ?? '';
            if (!isset($pages[$m[1]])) {
                $errors[] = "$context: dead link to {$m[1]}.md";
            } elseif ($anchor !== '' && !isset($pages[$m[1]]['toc'][$anchor])) {
                $errors[] = "$context: dead anchor {$m[1]}.md#$anchor";
            }
            return 'href="' . $m[1] . '.html' . ($anchor === '' ? '' : '#' . $anchor) . '"';
        }, $body);

        $body = (string) preg_replace_callback('#<p><img src="([^"]+)" alt="([^"]*)"(?: title="([^"]*)")? /></p>#', static function (array $m) use ($dir, $texts, &$warnings, $context): string {
            $caption = ($m[3] ?? '') === '' ? '' : '<figcaption>' . $m[3] . '</figcaption>';
            if (is_file($dir . '/' . $m[1])) {
                return '<figure><img src="' . $m[1] . '" alt="' . $m[2] . '" loading="lazy">' . $caption . '</figure>';
            }
            $warnings[] = "$context: image {$m[1]} is missing";
            return '<figure><div class="shot-missing">' . sprintf($texts['missing'], $m[2]) . '</div>' . $caption . '</figure>';
        }, $body);

        $body = (string) preg_replace_callback('#<blockquote>\s*<p>\[!(NOTE|WARNING)\]\s*(.*?)</blockquote>#s', static function (array $m) use ($icons, $texts): string {
            $kind = strtolower($m[1]);
            return '<div class="alert alert-' . $kind . '" role="note">' . $icons[$kind]
                . '<div><p><strong class="alert-label">' . $texts[$kind] . ':</strong> ' . $m[2] . '</div></div>';
        }, $body);

        // lists without bullets lose their role in VoiceOver
        $body = str_replace(['<ol>', '<th>', '<table>', '</table>'], ['<ol role="list">', '<th scope="col">', '<div class="table-scroll"><table>', '</table></div>'], $body);

        $position = array_search($name, $order, true);
        $titleOf = static function (?string $other) use ($pages): ?array {
            return $other === null ? null : ['slug' => $other, 'title' => $pages[$other]['title'] ?? $other];
        };
        $others = [];
        foreach ($languages as $otherLang => $other) {
            if (!isset($other['pages'][$name])) {
                $warnings[] = "$otherLang/$name.md: page exists in $lang only";
            }
            $others[] = [
                'name'    => $ui[$otherLang]['name'],
                'lang'    => $otherLang,
                'current' => $otherLang === $lang,
                'href'    => '../' . $otherLang . '/' . (isset($other['pages'][$name]) ? $name : 'index') . '.html',
            ];
        }
        $p = [
            'lang'    => $lang,
            'ui'      => $texts,
            'version' => $version,
            'name'    => $name,
            'title'   => $page['title'],
            'lead'    => $page['lead'],
            'body'    => $body,
            'toc'     => $page['toc'],
            'applies' => array_filter(array_map('trim', explode(',', $page['meta']['applies'] ?? '')), static fn (string $item): bool => $item !== ''),
            'since'   => $page['meta']['since'] ?? '',
            'nav'     => $language['nav'],
            'prev'    => $position === false ? null : $titleOf($order[$position - 1] ?? null),
            'next'    => $position === false ? null : $titleOf($order[$position + 1] ?? null),
            'languages' => $others,
        ];
        if ($errors === []) {
            @mkdir($target . '/' . $lang, 0777, true);
            file_put_contents($target . '/' . $lang . '/' . $name . '.html', $render($p));
        }
    }
    if ($errors === [] && is_dir($dir . '/img')) {
        @mkdir($target . '/' . $lang . '/img', 0777, true);
        foreach ((array) glob($dir . '/img/*') as $image) {
            copy((string) $image, $target . '/' . $lang . '/img/' . basename((string) $image));
        }
    }
}

foreach (array_unique($warnings) as $warning) {
    echo "warning: $warning\n";
}
if ($errors !== []) {
    fwrite(STDERR, 'error: ' . implode("\nerror: ", array_unique($errors)) . "\n");
    exit(1);
}
$count = array_sum(array_map(static fn (array $language): int => count($language['pages']), $languages));
echo "$count help pages → $target\n";
