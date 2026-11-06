<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Controller;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpCrawler;
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
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user->authorise('joomcookies.scan', 'com_joomcookies') && !$user->authorise('core.admin', 'com_joomcookies')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $start = trim((string) $this->input->post->getString('start_url', ''));

        if ($start === '') {
            $start = (string) SettingsStore::get('scan_start_url', Uri::root());
        }

        $host = (string) parse_url(Uri::root(), PHP_URL_HOST);
        $guard = new SsrfGuard([$host], true);

        try {
            $safeStart = $guard->assertSafe($start);
        } catch (\InvalidArgumentException) {
            $this->setMessage(Text::_('COM_JOOMCOOKIES_SCAN_URL_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=scan', false));

            return;
        }

        $options = [
            'max_pages'   => (int) SettingsStore::get('scan_max_pages', 10),
            'max_depth'   => (int) SettingsStore::get('scan_max_depth', 2),
            'max_seconds' => (int) SettingsStore::get('scan_max_seconds', 25),
            'timeout'     => 8,
            'max_bytes'   => 400000,
            'delay_ms'    => 150,
        ];

        $db  = Factory::getContainer()->get(DatabaseInterface::class);
        $now = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__joomcookies_scans'))
            ->columns(['started_at', 'status', 'mode', 'start_url', 'config', 'created_by'])
            ->values(implode(',', [
                $db->quote($now),
                $db->quote('running'),
                $db->quote('http'),
                $db->quote($safeStart),
                $db->quote(json_encode($options) ?: '{}'),
                (int) $user->id,
            ]));
        $db->setQuery($query)->execute();
        $scanId = (int) $db->insertid();

        $crawler = new HttpCrawler();
        $result  = $crawler->run($safeStart, $options, new StreamHttpTransport(), $guard);

        foreach ($result['findings'] as $finding) {
            $this->storeFinding($db, $scanId, $finding);
        }

        $summary = [
            'pages'    => \count($result['pages']),
            'findings' => \count($result['findings']),
            'errors'   => \count($result['errors']),
            'elapsed'  => $result['elapsed'],
        ];

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__joomcookies_scans'))
            ->set($db->quoteName('finished_at') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->set($db->quoteName('status') . ' = ' . $db->quote('complete'))
            ->set($db->quoteName('summary') . ' = ' . $db->quote(json_encode($summary) ?: '{}'))
            ->where($db->quoteName('id') . ' = ' . $scanId);
        $db->setQuery($query)->execute();

        $this->log($db, 'scan', 'HTTP scan completed');

        $this->setMessage(Text::sprintf('COM_JOOMCOOKIES_SCAN_DONE', $summary['pages'], $summary['findings']));
        $this->setRedirect(Route::_('index.php?option=com_joomcookies&view=scan&id=' . $scanId, false));
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

    /**
     * @param  array<string, mixed>  $finding
     */
    private function storeFinding(DatabaseInterface $db, int $scanId, array $finding): void
    {
        $meta = json_encode($finding['meta'] ?? [], JSON_UNESCAPED_SLASHES) ?: '{}';
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__joomcookies_findings'))
            ->columns([
                'scan_id', 'type', 'name', 'domain', 'page_url', 'target',
                'category_suggested', 'service_id', 'provider', 'evidence_kind', 'observed', 'meta',
            ])
            ->values(implode(',', [
                $scanId,
                $db->quote((string) $finding['type']),
                $db->quote(mb_substr((string) $finding['name'], 0, 191)),
                $db->quote(mb_substr((string) $finding['domain'], 0, 191)),
                $db->quote(mb_substr((string) $finding['page_url'], 0, 512)),
                $db->quote(mb_substr((string) $finding['target'], 0, 512)),
                $db->quote((string) ($finding['category_suggested'] ?? '')),
                $db->quote((string) ($finding['service_id'] ?? '')),
                $db->quote((string) ($finding['provider'] ?? '')),
                $db->quote((string) ($finding['evidence_kind'] ?? 'reference')),
                (int) ($finding['observed'] ?? 0),
                $db->quote($meta),
            ]));
        $db->setQuery($query)->execute();
    }

    private function log(DatabaseInterface $db, string $event, string $message): void
    {
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__joomcookies_logs'))
            ->columns(['created_at', 'level', 'event', 'message'])
            ->values(implode(',', [
                $db->quote(Factory::getDate()->toSql()),
                $db->quote('info'),
                $db->quote($event),
                $db->quote(mb_substr($message, 0, 512)),
            ]));
        $db->setQuery($query)->execute();
    }
}
