<?php

declare(strict_types=1);

$root    = __DIR__;
$plugin  = $root . '/extensions/plg_system_joomcookies';
$package = $root . '/package';
$dist    = $root . '/dist';
$tmp     = $root . '/build/tmp';

foreach ([$dist, $tmp, $package . '/packages'] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create {$dir}\n");
        exit(1);
    }
}

$pluginZip    = $package . '/packages/plg_system_joomcookies.zip';
$componentZip = $package . '/packages/com_joomcookies.zip';
$packageZip   = $dist . '/pkg_joomcookies-2.0.2.zip';
$component    = $root . '/extensions/com_joomcookies';

foreach ([$pluginZip, $componentZip, $packageZip] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

zipDirectory($plugin, $pluginZip);
zipDirectory($component, $componentZip);
zipFiles([
    $package . '/pkg_joomcookies.xml' => 'pkg_joomcookies.xml',
    $package . '/script.php' => 'script.php',
    $package . '/LICENSE.txt' => 'LICENSE.txt',
    $package . '/language/en-GB/pkg_joomcookies.sys.ini' => 'language/en-GB/pkg_joomcookies.sys.ini',
    $package . '/language/it-IT/pkg_joomcookies.sys.ini' => 'language/it-IT/pkg_joomcookies.sys.ini',
    $componentZip => 'packages/com_joomcookies.zip',
    $pluginZip => 'packages/plg_system_joomcookies.zip',
], $packageZip);

echo "Plugin ZIP: {$pluginZip}\n";
echo "Package ZIP: {$packageZip}\n";
echo 'SHA-256: ' . hash_file('sha256', $packageZip) . "\n";

function zipDirectory(string $source, string $destination): void
{
    $zip = new ZipArchive();

    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Cannot write {$destination}\n");
        exit(1);
    }

    $source = realpath($source);
    $files  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($files as $file) {
        $path    = $file->getRealPath();
        $relative = str_replace('\\', '/', substr($path, strlen($source) + 1));

        if ($file->isDir()) {
            $zip->addEmptyDir($relative);
            continue;
        }

        $zip->addFile($path, $relative);
    }

    $zip->close();
}

/**
 * @param  array<string, string>  $files
 */
function zipFiles(array $files, string $destination): void
{
    $zip = new ZipArchive();

    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Cannot write {$destination}\n");
        exit(1);
    }

    foreach ($files as $absolute => $relative) {
        if (!is_file($absolute)) {
            fwrite(STDERR, "Missing {$absolute}\n");
            exit(1);
        }

        $zip->addFile($absolute, $relative);
    }

    $zip->close();
}
