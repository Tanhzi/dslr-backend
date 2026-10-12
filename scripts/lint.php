<?php

/**
 * Lint toàn bộ PHP của dự án (L1): chạy `php -l` từng file.
 * Chạy: composer lint  |  php scripts/lint.php
 * Cross-platform (Windows/Linux) — không cần bash `find`.
 */

$root = dirname(__DIR__);
$dirs = ['app', 'routes', 'config', 'database', 'tests', 'scripts'];
$exts = ['php'];

$files = [];
foreach ($dirs as $dir) {
    $path = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), $exts, true)) {
            $files[] = $file->getPathname();
        }
    }
}

$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
$errors = 0;

foreach ($files as $file) {
    $cmd = escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        $errors++;
        echo implode(PHP_EOL, $output) . PHP_EOL;
    }
    $output = [];
}

$total = count($files);
if ($errors > 0) {
    echo "LINT FAIL: {$errors}/{$total} file loi." . PHP_EOL;
    exit(1);
}

echo "LINT OK: {$total} file khong loi." . PHP_EOL;
exit(0);
