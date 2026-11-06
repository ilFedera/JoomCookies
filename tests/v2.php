<?php

declare(strict_types=1);

use JoomCookies\Component\Joomcookies\Administrator\Service\Access\AccessGate;
use JoomCookies\Component\Joomcookies\Administrator\Service\Migration\PluginMigration;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\CookieHeaderParser;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HtmlAnalyzer;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpCrawler;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpResponse;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpTransport;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanComparer;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ServiceCatalog;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\SsrfGuard;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UnavailableBrowserScanDriver;
use JoomCookies\Plugin\System\Joomcookies\Service\HtmlGuard;
use JoomCookies\Plugin\System\Joomcookies\Service\ServiceRegistry;

$guard = new SsrfGuard(['example.test'], false);
assertTrue($guard->assertSafe('https://example.test/page') === 'https://example.test/page', 'Allowed host passes SSRF guard');

foreach (['http://127.0.0.1/', 'https://localhost/', 'http://169.254.169.254/', 'https://10.0.0.5/', 'https://192.168.1.8/', 'https://evil.test/', 'file:///etc/passwd', 'https://user:pass@example.test/'] as $bad) {
    $denied = false;
    try {
        $guard->assertSafe($bad);
    } catch (InvalidArgumentException) {
        $denied = true;
    }
    assertTrue($denied, 'SSRF blocked ' . $bad);
}

assertTrue(AccessGate::allows(['core.manage'], 'joomcookies.scan') === false, 'Scan ACL denied without privilege');
assertTrue(AccessGate::allows(['joomcookies.scan'], 'joomcookies.scan'), 'Scan ACL allowed');
assertTrue(AccessGate::allows(['core.admin'], 'joomcookies.scan'), 'Admin ACL inherits');
assertTrue(AccessGate::validToken('abc', 'abc'), 'CSRF token match');
assertTrue(!AccessGate::validToken('abc', 'xyz'), 'CSRF token mismatch');
assertTrue(!AccessGate::validToken('', 'abc'), 'Empty CSRF token rejected');

$plugin = ['consent_version' => '3', 'text_title' => 'From plugin', 'position' => 'modal', 'services' => [['id' => 'custom', 'name' => 'Custom', 'category' => 'statistics', 'match_type' => 'domain', 'match_value' => 'stats.example.test']]];
$first  = PluginMigration::apply($plugin, []);
assertTrue($first['consent_version'] === '3', 'Migration copies plugin consent version');
assertTrue($first['text_title'] === 'From plugin', 'Migration copies plugin texts');
assertTrue(!empty($first[PluginMigration::FLAG_KEY]['source_hash']), 'Migration stores source hash');
assertTrue(!PluginMigration::shouldMigrate($first, $plugin), 'Second migration is skipped (idempotent)');
$withBanner = PluginMigration::apply($plugin, ['position' => 'top', 'consent_version' => '']);
assertTrue($withBanner['position'] === 'top', 'Existing component values are kept');
assertTrue($withBanner['consent_version'] === '3', 'Empty component values are filled from plugin');
$forced = PluginMigration::apply($plugin, ['position' => 'top'], true);
assertTrue($forced['position'] === 'modal', 'Forced migration overwrites overlapping keys');
$reversed = PluginMigration::reverse(['position' => 'box-left']);
assertTrue($reversed['position'] === 'box-left', 'Rollback restores snapshot');

$hit = ServiceCatalog::identify('https://www.googletagmanager.com/gtag/js?id=G-TEST');
assertTrue($hit !== null && $hit['id'] === 'gtm' && $hit['category'] === 'statistics', 'Catalogue identifies GTM');
$unknown = ServiceCatalog::identify('https://unknown-tracker.example/x.js');
assertTrue($unknown === null, 'Unknown host is not auto-classified');

$html = '<html><body><script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>'
    . '<iframe src="https://www.youtube.com/embed/aaaaaaaaaaa"></iframe>'
    . '<script>document.cookie="demo_cookie=1"; localStorage.setItem("k","v");</script>'
    . '<a href="/about">About</a></body></html>';
$findings = HtmlAnalyzer::analyze($html, 'https://example.test/', 'example.test');
$types = array_column($findings, 'type');
assertTrue(\in_array('script', $types, true), 'Analyzer finds scripts');
assertTrue(\in_array('iframe', $types, true), 'Analyzer finds iframes');
assertTrue(\in_array('cookie_reference', $types, true), 'Analyzer finds cookie references');
assertTrue(\in_array('storage_reference', $types, true), 'Analyzer finds storage references');
$cookieRef = array_values(array_filter($findings, static fn ($row) => $row['type'] === 'cookie_reference'))[0];
assertTrue((int) $cookieRef['observed'] === 0, 'Cookie reference is not marked observed');

$headers = CookieHeaderParser::parse(['set-cookie' => ['session=abc; Path=/; Max-Age=3600; Domain=example.test']]);
assertTrue($headers[0]['name'] === 'session' && $headers[0]['max_age'] === 3600, 'Set-Cookie header parsed');

$diff = ScanComparer::diff(
    [['type' => 'script', 'name' => 'gtm', 'domain' => 'googletagmanager.com', 'service_id' => 'gtm']],
    [['type' => 'script', 'name' => 'gtm', 'domain' => 'googletagmanager.com', 'service_id' => 'gtm'], ['type' => 'iframe', 'name' => 'youtube', 'domain' => 'youtube.com', 'service_id' => 'youtube']]
);
assertTrue(\count($diff['added']) === 1 && $diff['unchanged'] === 1, 'Scan comparison detects additions');

$driver = new UnavailableBrowserScanDriver();
assertTrue($driver->isAvailable() === false, 'Browser driver is not available');
$threw = false;
try {
    $driver->run('https://example.test', []);
} catch (RuntimeException) {
    $threw = true;
}
assertTrue($threw, 'Browser driver does not invent results');

$transport = new class () implements HttpTransport {
    public function get(string $url, int $timeout, int $maxBytes): HttpResponse
    {
        if (str_contains($url, 'sitemap.xml') || str_contains($url, 'robots.txt')) {
            return new HttpResponse(404, '', [], $url);
        }

        $html = '<html><body><script src="https://www.google-analytics.com/analytics.js"></script>'
            . '<a href="https://example.test/about">About</a></body></html>';

        return new HttpResponse(200, $html, ['set-cookie' => ['joomla_user_state=logged_in; Path=/']], $url);
    }
};

$result = (new HttpCrawler())->run('https://example.test/', [
    'max_pages' => 2,
    'max_depth' => 1,
    'max_seconds' => 5,
    'timeout' => 2,
    'max_bytes' => 20000,
    'delay_ms' => 0,
], $transport, new SsrfGuard(['example.test'], false));

$crawlerTypes = array_column($result['findings'], 'type');
assertTrue(\in_array('script', $crawlerTypes, true), 'Crawler records third-party script');
assertTrue(\in_array('cookie_header', $crawlerTypes, true), 'Crawler records observed Set-Cookie');
$observed = array_values(array_filter($result['findings'], static fn ($row) => $row['type'] === 'cookie_header'))[0];
assertTrue((int) $observed['observed'] === 1, 'Set-Cookie finding is observed');
$scriptFinding = array_values(array_filter($result['findings'], static fn ($row) => $row['type'] === 'script'))[0];
assertTrue((int) $scriptFinding['observed'] === 0, 'Script presence is not treated as cookie proof');

$guardHtml = (new HtmlGuard(new ServiceRegistry([[
    'id' => 'approved-extra',
    'name' => 'Approved extra',
    'category' => 'marketing',
    'match_type' => 'domain',
    'match_value' => 'ads.example.test',
    'cookies' => 'adtok',
]]), ['youtube_nocookie' => true, 'youtube_thumbnails' => 'local'], []))->process(
    '<script src="https://ads.example.test/x.js"></script><script src="/media/system/js/core.min.js"></script>'
);
assertTrue(str_contains($guardHtml, 'data-jck-src="https://ads.example.test/x.js"'), 'Approved extra service is blocked');
assertTrue(str_contains($guardHtml, '/media/system/js/core.min.js'), 'Essential Joomla script stays executable');
