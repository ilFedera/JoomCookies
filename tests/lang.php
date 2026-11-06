<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pairs = [
    $root . '/extensions/com_joomcookies/admin' => [
        $root . '/extensions/com_joomcookies/admin/language/en-GB/com_joomcookies.ini',
        $root . '/extensions/com_joomcookies/admin/language/it-IT/com_joomcookies.ini',
    ],
    $root . '/extensions/plg_system_joomcookies' => [
        $root . '/extensions/plg_system_joomcookies/language/en-GB/plg_system_joomcookies.ini',
        $root . '/extensions/plg_system_joomcookies/language/it-IT/plg_system_joomcookies.ini',
    ],
];

function langKeys(string $file): array
{
    $keys = [];
    foreach (file($file) ?: [] as $line) {
        if (preg_match('/^([A-Z0-9_]+)="/', $line, $match) === 1) {
            $keys[$match[1]] = true;
        }
    }

    return $keys;
}

function usedKeys(string $dir, string $prefix): array
{
    $keys = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (!$file->isFile() || !preg_match('/\.(php|xml)$/i', $file->getFilename())) {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';

        if (preg_match_all('/\b(' . $prefix . '[A-Z0-9_]+)/', $contents, $matches)) {
            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }
    }

    return $keys;
}

foreach ($pairs as $source => $inis) {
    $prefix = str_contains($source, 'plg_system') ? 'PLG_SYSTEM_JOOMCOOKIES_' : 'COM_JOOMCOOKIES_';
    $used = usedKeys($source, $prefix);

    foreach ($inis as $ini) {
        $defined = langKeys($ini);
        $missing = array_diff_key($used, $defined);
        // XML description and name keys live in .sys.ini
        unset($missing[$prefix === 'COM_JOOMCOOKIES_' ? 'COM_JOOMCOOKIES' : 'PLG_SYSTEM_JOOMCOOKIES'], $missing[$prefix . 'XML_DESCRIPTION']);
        assertTrue($missing === [], basename($ini) . ' missing keys: ' . implode(', ', array_keys($missing)));
        assertTrue(isset($defined[$prefix === 'COM_JOOMCOOKIES_' ? 'COM_JOOMCOOKIES_NAV_DASHBOARD' : 'PLG_SYSTEM_JOOMCOOKIES_UI_TITLE']), basename($ini) . ' has core keys');
    }
}

$sys = [
    $root . '/extensions/com_joomcookies/admin/language/en-GB/com_joomcookies.sys.ini',
    $root . '/extensions/com_joomcookies/admin/language/it-IT/com_joomcookies.sys.ini',
    $root . '/extensions/plg_system_joomcookies/language/en-GB/plg_system_joomcookies.sys.ini',
    $root . '/extensions/plg_system_joomcookies/language/it-IT/plg_system_joomcookies.sys.ini',
    $root . '/package/language/en-GB/pkg_joomcookies.sys.ini',
    $root . '/package/language/it-IT/pkg_joomcookies.sys.ini',
];

foreach ($sys as $file) {
    assertTrue(is_file($file) && filesize($file) > 40, 'Language sys file exists: ' . basename(dirname($file)) . '/' . basename($file));
}
