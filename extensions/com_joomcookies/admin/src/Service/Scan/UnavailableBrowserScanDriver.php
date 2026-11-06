<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

final class UnavailableBrowserScanDriver implements BrowserScanDriver
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function describe(): string
    {
        if (class_exists(Text::class)) {
            $translated = Text::_('COM_JOOMCOOKIES_SCAN_BROWSER_UNAVAILABLE');

            if ($translated !== 'COM_JOOMCOOKIES_SCAN_BROWSER_UNAVAILABLE') {
                return $translated;
            }
        }

        return 'La scansione browser avanzata richiede un worker Playwright/Chromium separato. Non è inclusa in JoomCookies e non deve essere eseguita nella richiesta PHP su hosting condiviso.';
    }

    public function run(string $startUrl, array $options): array
    {
        throw new \RuntimeException($this->describe());
    }
}
