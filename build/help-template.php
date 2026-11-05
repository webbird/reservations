<?php

/**
 * One page of the user help, included by help.php.
 *
 * @var array<string, mixed> $p   page data, see help.php
 * @var callable(string): string $e
 */

declare(strict_types=1);

$home = $p['name'] === 'index';
// the sidebar and the phone menu show the same list; CSS leaves one of them in place
$topicList = static function () use ($p, $e): string {
    $html = '';
    foreach ($p['nav'] as $group) {
        $html .= '<h2>' . $e($group['title']) . '</h2><ul>';
        foreach ($group['pages'] as $page) {
            $current = $page['slug'] === $p['name'] ? ' aria-current="page"' : '';
            $html .= '<li><a href="' . $e($page['slug']) . '.html"' . $current . '>' . $e($page['title']) . '</a></li>';
        }
        $html .= '</ul>';
    }
    return $html;
};
?>
<!doctype html>
<html lang="<?= $e($p['lang']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $home ? $e($p['ui']['site']) : strip_tags($p['title']) . ' – ' . $e($p['ui']['site']) ?></title>
<link rel="stylesheet" href="../help.css">
</head>
<body>
<a class="skip" href="#content"><?= $e($p['ui']['skip']) ?></a>
<header class="site">
    <div class="wrap">
        <a class="brand" href="index.html">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="22" height="20" rx="3"/><path d="M3 11h22M9 3v4M19 3v4M9 16h3M16 16h3M9 20h3"/></svg>
            <span><strong>Reservations</strong> <?= $e($p['ui']['help']) ?></span>
        </a>
        <div class="tools">
            <span class="chip"><?= $e(sprintf($p['ui']['version'], $p['version'])) ?></span>
            <?php if (count($p['languages']) > 1) : ?>
                <nav aria-label="<?= $e($p['ui']['language']) ?>">
                    <?php foreach ($p['languages'] as $language) : ?>
                        <a href="<?= $e($language['href']) ?>" lang="<?= $e($language['lang']) ?>" hreflang="<?= $e($language['lang']) ?>"<?= $language['current'] === true ? ' aria-current="page"' : '' ?>><?= $e($language['name']) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if ($home) : ?>
    <main id="content" class="home">
        <div class="hero">
            <div class="wrap">
                <h1><?= $p['title'] ?></h1>
                <?php if ($p['lead'] !== '') : ?><p class="lead"><?= $p['lead'] ?></p><?php endif; ?>
            </div>
        </div>
        <div class="wrap content"><?= $p['body'] ?></div>
    </main>
<?php else : ?>
    <details class="topics-phone">
        <summary><?= $e($p['ui']['topics']) ?></summary>
        <nav aria-label="<?= $e($p['ui']['topics']) ?>"><?= $topicList() ?></nav>
    </details>
    <div class="wrap layout">
        <nav class="sidebar" aria-label="<?= $e($p['ui']['topics']) ?>"><?= $topicList() ?></nav>
        <main id="content">
            <nav class="path" aria-label="<?= $e($p['ui']['path']) ?>"><a href="index.html"><?= $e($p['ui']['help']) ?></a> <span aria-hidden="true">›</span> <span aria-current="page"><?= $p['title'] ?></span></nav>
            <h1><?= $p['title'] ?></h1>
            <?php if ($p['lead'] !== '') : ?><p class="lead"><?= $p['lead'] ?></p><?php endif; ?>
            <?php if ($p['applies'] !== [] || $p['since'] !== '') : ?>
                <ul class="chips" aria-label="<?= $e($p['ui']['applies']) ?>">
                    <?php foreach ($p['applies'] as $item) : ?><li class="chip"><?= $e($item) ?></li><?php endforeach; ?>
                    <?php if ($p['since'] !== '') : ?><li class="chip"><?= $e(sprintf($p['ui']['since'], $p['since'])) ?></li><?php endif; ?>
                </ul>
            <?php endif; ?>
            <?php if (count($p['toc']) > 1) : ?>
                <nav class="toc" aria-labelledby="toc-title">
                    <h2 id="toc-title"><?= $e($p['ui']['on_page']) ?></h2>
                    <ol>
                        <?php foreach ($p['toc'] as $id => $heading) : ?><li><a href="#<?= $e((string) $id) ?>"><?= $heading ?></a></li><?php endforeach; ?>
                    </ol>
                </nav>
            <?php endif; ?>
            <div class="content"><?= $p['body'] ?></div>
            <?php if ($p['prev'] !== null || $p['next'] !== null) : ?>
                <nav class="turn" aria-label="<?= $e($p['ui']['turn']) ?>">
                    <?php if ($p['prev'] !== null) : ?><a href="<?= $e($p['prev']['slug']) ?>.html"><span><?= $e($p['ui']['back']) ?></span> ‹ <?= $p['prev']['title'] ?></a><?php endif; ?>
                    <?php if ($p['next'] !== null) : ?><a class="next" href="<?= $e($p['next']['slug']) ?>.html"><span><?= $e($p['ui']['next']) ?></span> <?= $p['next']['title'] ?> ›</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </main>
    </div>
<?php endif; ?>

<footer class="site">
    <div class="wrap">
        <span><?= $e(sprintf($p['ui']['footer'], $p['version'])) ?></span>
        <span><?= $e($p['ui']['offline']) ?></span>
    </div>
</footer>
</body>
</html>
