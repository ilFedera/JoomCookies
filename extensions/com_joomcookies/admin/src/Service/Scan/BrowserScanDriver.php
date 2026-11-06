<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

/**
 * Optional Chromium/Playwright contract. Not executed on typical Joomla hosting.
 */
interface BrowserScanDriver
{
    public function isAvailable(): bool;

    public function describe(): string;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(string $startUrl, array $options): array;
}
