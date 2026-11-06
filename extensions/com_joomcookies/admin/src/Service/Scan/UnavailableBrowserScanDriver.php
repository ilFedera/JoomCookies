<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

final class UnavailableBrowserScanDriver implements BrowserScanDriver
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function describe(): string
    {
        return 'Advanced browser scanning requires a separate Playwright/Chromium worker. It is not bundled with JoomCookies and must not run inside the PHP request on shared hosting.';
    }

    public function run(string $startUrl, array $options): array
    {
        throw new \RuntimeException($this->describe());
    }
}
