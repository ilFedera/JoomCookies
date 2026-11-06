<?php

declare(strict_types=1);

use JoomCookies\Component\Joomcookies\Administrator\Service\Access\AccessGate;
use JoomCookies\Component\Joomcookies\Administrator\Service\Migration\PluginMigration;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\CookieHeaderParser;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\FindingAggregator;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HtmlAnalyzer;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpCrawler;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanLimits;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpResponse;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpTransport;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanComparer;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ServiceCatalog;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\SsrfGuard;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UnavailableBrowserScanDriver;
use JoomCookies\Plugin\System\Joomcookies\Service\HtmlGuard;
use JoomCookies\Plugin\System\Joomcookies\Service\ServiceRegistry;

$installSql = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/sql/install.mysql.utf8.sql') ?: '';
assertTrue(!str_contains($installSql, "INSERT INTO `#__joomcookies_categories`"), 'Install SQL does not seed categories (avoids duplicate alias)');
assertTrue(str_contains($installSql, 'joomcookies_scan_queue'), 'Install SQL creates the scan queue table');

$pluginClassFile = dirname(__DIR__) . '/extensions/plg_system_joomcookies/src/Extension/Joomcookies.php';
$bridgeFile      = dirname(__DIR__) . '/extensions/plg_system_joomcookies/src/Service/ComponentConfigBridge.php';
$pluginXml       = file_get_contents(dirname(__DIR__) . '/extensions/plg_system_joomcookies/joomcookies.xml') ?: '';
$pluginSrc       = file_get_contents($pluginClassFile) ?: '';
$bridgeSrc       = file_get_contents($bridgeFile) ?: '';

assertTrue(is_file($bridgeFile), 'ComponentConfigBridge.php exists in plugin src/Service');
assertTrue(str_contains($bridgeSrc, 'namespace JoomCookies\\Plugin\\System\\Joomcookies\\Service;'), 'Bridge namespace is Service, not Extension');
assertTrue(str_contains($pluginSrc, 'use JoomCookies\\Plugin\\System\\Joomcookies\\Service\\ComponentConfigBridge;'), 'Plugin imports ComponentConfigBridge from Service');
assertTrue(str_contains($pluginXml, '<namespace path="src">JoomCookies\\Plugin\\System\\Joomcookies</namespace>'), 'Plugin manifest registers PSR-4 namespace');
assertTrue(class_exists(\JoomCookies\Plugin\System\Joomcookies\Service\ComponentConfigBridge::class, true), 'Bridge class autoloads');
assertTrue(!class_exists(\JoomCookies\Plugin\System\Joomcookies\Extension\ComponentConfigBridge::class, false), 'Bridge is not registered under Extension');
assertTrue(str_contains($pluginSrc, 'ensureFrontendAssets'), 'Plugin injects CSS/JS in onAfterRender if head assets are missing');
assertTrue(str_contains($pluginSrc, 'onAfterDispatch'), 'Plugin registers assets before YOOtheme compiles the head');

$js = file_get_contents(dirname(__DIR__) . '/extensions/plg_system_joomcookies/media/js/joomcookies.js') ?: '';
assertTrue(str_contains($js, 'window.JoomCookies'), 'Public JoomCookies API is exposed');
assertTrue(str_contains($js, 'hasConsent:'), 'hasConsent is part of the public API');
assertTrue(str_contains($js, '_attached'), 'Script guards against double initialisation');

$dashboard = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/tmpl/dashboard/default.php') ?: '';
assertTrue(!str_contains($dashboard, 'COM_JOOMCOOKIES_MIGRATION'), 'Dashboard does not show V1 migration UI');

$defaults = \JoomCookies\Component\Joomcookies\Administrator\Service\DefaultCopy::settings();
assertTrue($defaults['text_title'] === 'La tua privacy è importante', 'Default banner title is Italian');
assertTrue(str_contains((string) $defaults['text_message'], 'solo con il tuo consenso'), 'Default banner message is Italian');
assertTrue($defaults['text_youtube_prefs'] === 'Gestisci preferenze', 'Default YouTube preferences button is Italian');
assertTrue($defaults['text_youtube_help'] === 'Attiva i contenuti esterni per visualizzarlo.', 'Default YouTube help is short');

$css = file_get_contents(dirname(__DIR__) . '/extensions/plg_system_joomcookies/media/css/joomcookies.css') ?: '';
assertTrue(str_contains($css, '2147483647'), 'Banner uses a maximum stacking z-index');
assertTrue(str_contains($css, 'calc(100% - 32px)'), 'Banner width is viewport-relative');
assertTrue(str_contains($css, 'max-width: 900px'), 'Banner has a readable max width');
assertTrue(!preg_match('/html\.jck-open\s*\{[^}]*overflow:\s*hidden/', $css), 'Simple banner does not lock html overflow');
assertTrue(str_contains($css, 'html.jck-prefs-open'), 'Preferences panel can lock page scroll');
assertTrue(str_contains($css, 'color: var(--jck-text) !important'), 'Title colour uses configured text token');
assertTrue(str_contains($js, 'attachToBody'), 'Banner root is moved under document.body');
assertTrue(str_contains($js, "lockPageScroll(which === 'panel')"), 'Page scroll lock is limited to the preferences panel');
assertTrue(!preg_match('/preventDefault\(\).*wheel|wheel.*preventDefault/', $js), 'Wheel events are not cancelled');
assertTrue(str_contains($js, "addEventListener('wheel', forwardPageScroll, { passive: true })"), 'Banner forwards wheel to the page without blocking it');

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
$yooScan = ServiceCatalog::identify('/templates/yootheme/vendor/yootheme/theme-analytics/app/analytics.min.js');
assertTrue($yooScan !== null && $yooScan['id'] === 'yootheme-analytics', 'Scan catalogue identifies YOOtheme analytics');
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
$incompleteDiff = ScanComparer::diff(
    [['type' => 'script', 'name' => 'gtm', 'domain' => 'googletagmanager.com', 'service_id' => 'gtm']],
    [],
    false
);
assertTrue($incompleteDiff['removed'] === [] && $incompleteDiff['incomplete'] === true, 'Incomplete scans do not mark services as removed');
$grouped = FindingAggregator::group([
    ['type' => 'script', 'name' => 'gtm', 'domain' => 'googletagmanager.com', 'service_id' => 'gtm', 'page_url' => 'https://example.test/a'],
    ['type' => 'script', 'name' => 'gtm', 'domain' => 'googletagmanager.com', 'service_id' => 'gtm', 'page_url' => 'https://example.test/b'],
]);
assertTrue(\count($grouped) === 1 && (int) $grouped[0]['occurrences'] === 2 && (int) $grouped[0]['page_count'] === 2, 'Findings are grouped by service');
assertTrue(ScanLimits::pageCap(['scan_mode' => 'complete', 'max_pages' => 50]) === 0, 'Complete scan has no page cap');
assertTrue(ScanLimits::pageCap(['scan_mode' => 'quick', 'max_pages' => 50]) === 50, 'Quick scan keeps the configured cap');
$crawlerSrc = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/src/Service/Scan/HttpCrawler.php') ?: '';
$scanCtrlSrc = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/src/Controller/ScanController.php') ?: '';
assertTrue(!str_contains($crawlerSrc, 'min(50') && !str_contains($scanCtrlSrc, 'min(50'), 'Hard 50-page cap is removed');
$js = file_get_contents(dirname(__DIR__) . '/extensions/plg_system_joomcookies/media/js/joomcookies.js') ?: '';
assertTrue(str_contains($js, 'enhanceYoutubePlayers'), 'Frontend enhances youtube-player containers');
assertTrue(str_contains($js, 'observeDynamicContent'), 'MutationObserver watches dynamically added videos');
assertTrue(str_contains($js, 'loadYoutubeIfAllowed'), 'YouTube iframes load when embeds are allowed');
assertTrue(str_contains($js, 'withoutAutoplay'), 'YouTube iframes do not autoplay');
assertTrue(!str_contains($js, 'isYoutubeEmbed(node) && !onlyNode'), 'Accept-all no longer leaves YouTube placeholders in place');
assertTrue(str_contains($js, 'youtubeBusy'), 'YouTube enhance has a reentrancy guard');
assertTrue(str_contains($js, 'takeRecords()'), 'Observer queue is drained while enhancing');
assertTrue(str_contains($js, 'isOwnYoutubeNode'), 'Observer ignores JoomCookies own DOM mutations');
assertTrue(!str_contains($js, "document.querySelectorAll('[data-jck-youtube]').forEach(refreshYoutubeActions)"), 'Enhance does not refresh every YouTube node on each mutation');
assertTrue(str_contains($js, 'JoomCookies: frontend init skipped'), 'Start errors are caught without breaking the page');
assertTrue(str_contains($js, 'renderYoutubePlaceholder(node, videoId)'), 'Revoked YouTube players restore placeholders');
assertTrue(str_contains($js, 'adaptYoothemeLoadQueue'), 'YOOtheme $load queue is adapted after statistics consent');
assertTrue(str_contains($js, 'updateGoogleConsentMode'), 'Consent Mode is updated from the visitor choice');
assertTrue(str_contains($js, 'isGoogleAnalyticsNetwork'), 'Dynamic gtag injection is recognized without blocking reCAPTCHA');
assertTrue(str_contains($js, "name.indexOf('_ga_') === 0"), 'GA4 _ga_* cookies are removed on revoke');
$pluginSrc = file_get_contents($pluginClassFile) ?: '';
assertTrue(str_contains($pluginSrc, 'ConsentState::deniedOptional()'), 'Public HTML is generated without reading the consent cookie');
assertTrue(str_contains($pluginSrc, 'GoogleConsentMode::injectAfterHeadOpen'), 'Consent Mode default is injected after HTML rewrite');
assertTrue(str_contains($pluginSrc, 'FrontendAssets::withVersion'), 'CSS/JS URLs are versioned after install');
assertTrue(str_contains($pluginSrc, 'FrontendAssets::protectionAttributes'), 'Injected tags use LiteSpeed protection attributes');
$assetClass = file_get_contents(dirname(__DIR__) . '/extensions/plg_system_joomcookies/src/Service/FrontendAssets.php') ?: '';
assertTrue(str_contains($assetClass, 'data-no-delay') && str_contains($assetClass, 'data-no-combine'), 'LiteSpeed delay/combine are opted out on JoomCookies assets');
$assetJson = file_get_contents(dirname(__DIR__) . '/extensions/plg_system_joomcookies/media/joomla.asset.json') ?: '';
assertTrue(str_contains($assetJson, '"2.2.6"') && str_contains($assetJson, 'data-no-delay'), 'WebAsset registry versions assets and skips LiteSpeed delay');
assertTrue(\JoomCookies\Plugin\System\Joomcookies\Service\FrontendAssets::version() === '2.2.6', 'FrontendAssets version is 2.2.6');

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

$sitemapTransport = new class () implements HttpTransport {
    public function get(string $url, int $timeout, int $maxBytes): HttpResponse
    {
        if (str_contains($url, 'robots.txt')) {
            return new HttpResponse(404, '', [], $url);
        }

        if (str_contains($url, 'sitemap.xml')) {
            $locs = '';
            for ($i = 1; $i <= 1000; $i++) {
                $locs .= '<url><loc>https://example.test/p' . $i . '</loc></url>';
            }

            return new HttpResponse(200, '<?xml version="1.0"?><urlset>' . $locs . '</urlset>', ['content-type' => 'application/xml'], $url);
        }

        return new HttpResponse(200, '<html><body>ok</body></html>', [], $url);
    }
};

$large = (new HttpCrawler())->run('https://example.test/', [
    'max_pages' => 80,
    'max_depth' => 0,
    'max_seconds' => 20,
    'timeout' => 2,
    'max_bytes' => 20000,
    'delay_ms' => 0,
    'scan_mode' => 'quick',
], $sitemapTransport, new SsrfGuard(['example.test'], false));
assertTrue(\count($large['pages']) === 80, 'Quick scan can exceed the old 50-page cap');

$complete = (new HttpCrawler())->run('https://example.test/', [
    'max_pages' => 50,
    'max_depth' => 0,
    'max_seconds' => 20,
    'timeout' => 2,
    'max_bytes' => 20000,
    'delay_ms' => 0,
    'scan_mode' => 'complete',
], $sitemapTransport, new SsrfGuard(['example.test'], false));
assertTrue(\count($complete['pages']) > 1000, 'Complete scan is not limited to 50, 80 or 1,000 pages');

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
