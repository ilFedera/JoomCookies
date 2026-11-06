<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Controller;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpCrawler;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanAjax;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanLimits;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanRunner;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanUrlStore;
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

        $discovery = (string) $this->input->post->getCmd('discovery', (string) SettingsStore::get('scan_discovery', ScanLimits::DISCOVERY_HTML));
        $options = [
            'scan_mode'   => $mode === ScanLimits::MODE_COMPLETE ? ScanLimits::MODE_COMPLETE : ScanLimits::MODE_QUICK,
            'discovery'   => ScanLimits::discovery(['discovery' => $discovery]),
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
        } catch (\InvalidArgumentException) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_URL_DENIED'));

            return;
        } catch (\Throwable) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_FAILED'));

            return;
        }

        $this->respond($payload, (int) $payload['scan_id']);
    }

    public function batch(): void
    {
        $this->authorize();
        $scanId = $this->scanId();

        if ($scanId < 1) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_FAILED'));

            return;
        }

        try {
            $this->respond($this->runner()->processBatch($scanId), $scanId);
        } catch (\Throwable) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_FAILED'));
        }
    }

    public function pause(): void
    {
        $this->runNamed('pause');
    }

    public function resume(): void
    {
        $this->runNamed('resume');
    }

    public function cancel(): void
    {
        $this->runNamed('cancel');
    }

    public function status(): void
    {
        $this->authorize();
        $scanId = $this->scanId();
        $this->respond($this->runner()->statusPayload($scanId), $scanId);
    }

    public function urlsCsv(): void
    {
        $this->authorize();
        $scanId = $this->scanId();

        if ($scanId < 1) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_FAILED'));

            return;
        }

        $store = new ScanUrlStore(Factory::getContainer()->get(DatabaseInterface::class));
        $rows = $store->export($scanId);
        $this->noCache();
        $this->app->setHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->app->setHeader('Content-Disposition', 'attachment; filename="joomcookies-scan-' . $scanId . '-urls.csv"');
        $this->app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache');
        $this->app->sendHeaders();
        $out = fopen('php://output', 'w');

        if ($out === false) {
            $this->app->close();
        }

        fputcsv($out, ['original_url', 'normalized', 'referrer_url', 'discovery', 'http_status', 'final_url', 'canonical_url', 'outcome', 'reason']);

        foreach ($rows as $row) {
            fputcsv($out, [
                (string) ($row['original_url'] ?? ''),
                (string) ($row['normalized'] ?? ''),
                (string) ($row['referrer_url'] ?? ''),
                (string) ($row['discovery'] ?? ''),
                (string) ($row['http_status'] ?? ''),
                (string) ($row['final_url'] ?? ''),
                (string) ($row['canonical_url'] ?? ''),
                (string) ($row['outcome'] ?? ''),
                (string) ($row['reason'] ?? ''),
            ]);
        }

        fclose($out);
        $this->app->close();
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

    private function runNamed(string $method): void
    {
        $this->authorize();
        $scanId = $this->scanId();

        if ($scanId < 1) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_FAILED'));

            return;
        }

        try {
            $this->respond($this->runner()->{$method}($scanId), $scanId);
        } catch (\Throwable) {
            $this->fail(Text::_('COM_JOOMCOOKIES_SCAN_FAILED'));
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
        $this->noCache();

        if ($this->wantsJson()) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $this->app->setHeader('Pragma', 'no-cache');
            $this->app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache');
            $this->app->sendHeaders();
            echo json_encode($payload, JSON_UNESCAPED_SLASHES);
            $this->app->close();
        }

        $status = (string) ($payload['status'] ?? '');
        $pages = (int) (($payload['progress']['pages'] ?? 0));
        $findings = (int) (($payload['progress']['findings'] ?? 0));

        if ($status === 'complete') {
            $this->setMessage(Text::sprintf('COM_JOOMCOOKIES_SCAN_DONE', $pages, $findings));
        } elseif (!empty($payload['error'])) {
            $this->setMessage((string) $payload['error'], 'error');
        } else {
            $this->setMessage(Text::_('COM_JOOMCOOKIES_SCAN_RUNNING'));
        }

        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=scan&id=' . $scanId, false));
    }

    private function fail(string $message): void
    {
        $this->noCache();

        if ($this->wantsJson()) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $this->app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache');
            $this->app->sendHeaders();
            echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
            $this->app->close();
        }

        $this->setMessage($message, 'error');
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=scan', false));
    }

    private function wantsJson(): bool
    {
        return ScanAjax::wantsJson();
    }

    private function noCache(): void
    {
        $this->app->allowCache(false);
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $this->app->setHeader('Pragma', 'no-cache');
        $this->app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache');
    }
}
