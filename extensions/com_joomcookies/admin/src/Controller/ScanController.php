<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Controller;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpCrawler;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanLimits;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanRunner;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\SsrfGuard;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\StreamHttpTransport;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UnavailableBrowserScanDriver;
use JoomCookies\Component\Joomcookies\Administrator\Service\SettingsStore;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

class ScanController extends BaseController
{
    public function start(): void
    {
        $this->authorize();
        $runner = $this->runner();
        $mode = (string) $this->input->post->getCmd('scan_mode', ScanLimits::MODE_QUICK);
        $start = trim((string) $this->input->post->getString('start_url', ''));

        if ($start === '') {
            $start = (string) SettingsStore::get('scan_start_url', Uri::root());
        }

        $host = (string) parse_url(Uri::root(), PHP_URL_HOST);
        $guard = new SsrfGuard([$host], true);

        try {
            $safeStart = $guard->assertSafe($start);
        } catch (\InvalidArgumentException) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_URL_DENIED'));

            return;
        }

        $options = [
            'scan_mode'   => $mode === ScanLimits::MODE_COMPLETE ? ScanLimits::MODE_COMPLETE : ScanLimits::MODE_QUICK,
            'max_pages'   => (int) SettingsStore::get('scan_max_pages', 50),
            'max_depth'   => (int) SettingsStore::get('scan_max_depth', 2),
            'max_seconds' => (int) SettingsStore::get('scan_max_seconds', 12),
            'timeout'     => 8,
            'max_bytes'   => 400000,
            'delay_ms'    => (int) SettingsStore::get('scan_delay_ms', 150),
            'batch_size'  => (int) SettingsStore::get('scan_batch_size', 8),
        ];

        if ($options['scan_mode'] === ScanLimits::MODE_COMPLETE) {
            $options['max_pages'] = 0;
            $options['max_depth'] = max($options['max_depth'], 5);
        }

        try {
            $payload = $runner->start($safeStart, $options, (int) $this->app->getIdentity()->id);
        } catch (\Throwable) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_URL_DENIED'));

            return;
        }

        $this->respond($payload, (int) $payload['scan_id']);
    }

    public function batch(): void
    {
        $this->authorize();
        $scanId = $this->scanId();
        $this->respond($this->runner()->processBatch($scanId), $scanId);
    }

    public function pause(): void
    {
        $this->authorize();
        $scanId = $this->scanId();
        $this->respond($this->runner()->pause($scanId), $scanId);
    }

    public function resume(): void
    {
        $this->authorize();
        $scanId = $this->scanId();
        $this->respond($this->runner()->resume($scanId), $scanId);
    }

    public function cancel(): void
    {
        $this->authorize();
        $scanId = $this->scanId();
        $this->respond($this->runner()->cancel($scanId), $scanId);
    }

    public function status(): void
    {
        $this->authorize();
        $scanId = $this->scanId();
        $this->respond($this->runner()->statusPayload($scanId), $scanId);
    }

    public function browserUnavailable(): void
    {
        $driver = new UnavailableBrowserScanDriver();
        $this->setMessage($driver->describe(), 'warning');
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=scan', false));
    }

    public function checkToken($method = 'post', $redirect = true)
    {
        if (!Session::checkToken($method)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return true;
    }

    private function authorize(): void
    {
        $this->checkToken();
        $user = $this->app->getIdentity();

        if (!$user->authorise('joomcookies.scan', 'com_joomcookies') && !$user->authorise('core.admin', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function runner(): ScanRunner
    {
        $host = (string) parse_url(Uri::root(), PHP_URL_HOST);
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return new ScanRunner($db, new HttpCrawler(), new StreamHttpTransport(), new SsrfGuard([$host], true));
    }

    private function scanId(): int
    {
        return (int) $this->input->post->getInt('scan_id', $this->input->getInt('id'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(array $payload, int $scanId): void
    {
        if ($this->wantsJson()) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->sendHeaders();
            echo json_encode($payload, JSON_UNESCAPED_SLASHES);
            $this->app->close();
        }

        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=scan&id=' . $scanId, false));
    }

    private function fail(string $message): void
    {
        if ($this->wantsJson()) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->sendHeaders();
            echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
            $this->app->close();
        }

        $this->setMessage($message, 'error');
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=scan', false));
    }

    private function wantsJson(): bool
    {
        return $this->input->getCmd('format') === 'json'
            || strtolower((string) $this->input->server->getString('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
    }
}
