<?php

/**
 * Builds the installable WBCE module: wbce/ (with the generated help) plus core/src as core/src inside it.
 * Usage: php build/build.php  →  build/dist/reservations-<version>.zip
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$info = (string) file_get_contents($root . '/wbce/info.php');
if (preg_match('/\$module_version\s*=\s*\'([^\']+)\'/', $info, $m) !== 1) {
    fwrite(STDERR, "No \$module_version in wbce/info.php\n");
    exit(1);
}
$target = __DIR__ . '/dist/reservations-' . $m[1] . '.zip';
@mkdir(dirname($target), 0777, true);

// the user help is generated into wbce/help/ and goes into the zip with the rest of wbce/
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/help.php'), $helpResult);
if ($helpResult !== 0) {
    exit(1);
}

if (is_file($target)) {
    unlink($target);
}

$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $target\n");
    exit(1);
}

$add = static function (string $dir, string $prefix) use ($zip): int {
    $count = 0;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isFile()) {
            // WBCE's installer rejects backslashes in zip paths
            $zip->addFile($file->getPathname(), $prefix . str_replace('\\', '/', $files->getSubPathname()));
            $count++;
        }
    }
    return $count;
};

$count = $add($root . '/wbce', '') + $add($root . '/core/src', 'core/src/');

// WBCE convention: every directory gets an index.php that redirects away
$dirs = ['core', 'core/src'];
$tree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/core/src', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($tree as $item) {
    if ($item->isDir()) {
        $dirs[] = 'core/src/' . str_replace('\\', '/', $tree->getSubPathname());
    }
}
foreach ($dirs as $dir) {
    $zip->addFromString($dir . '/index.php', "<?php\nheader('Location: " . str_repeat('../', substr_count($dir, '/') + 1) . "');\nexit;\n");
}
$zip->close();

echo "$count files → $target\n";
