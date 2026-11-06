<?php

declare(strict_types=1);

use JoomCookies\Component\Joomcookies\Administrator\Service\Catalog\CatalogSync;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\CookieClassifier;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HtmlAnalyzer;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpCrawler;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpResponse;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\HttpTransport;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanAjax;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanLimits;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ServiceCatalog;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\SsrfGuard;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\UrlPolicy;

assertTrue(ScanAjax::wantsJson(['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']) === true, 'XHR header is treated as AJAX');
assertTrue(ScanAjax::wantsJson(['REDIRECT_HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']) === true, 'LiteSpeed redirected XHR header is treated as AJAX');
assertTrue(ScanAjax::wantsJson(['HTTP_ACCEPT' => 'application/json']) === true, 'JSON Accept is treated as AJAX');
assertTrue(ScanAjax::wantsJson(['HTTP_ACCEPT' => 'text/html']) === false, 'HTML form posts are not treated as AJAX');
assertTrue(ScanLimits::maxSeconds(['max_seconds' => 120]) === 25, 'Per-request time cap stays at 25 seconds');
assertTrue(ScanLimits::maxSeconds(['max_seconds' => 1]) === 3, 'Per-request time floor is 3 seconds');
assertTrue(ScanLimits::pageCap(['scan_mode' => 'complete', 'max_pages' => 50]) === 0, 'Full scan ignores the quick page cap');
assertTrue(ScanLimits::allowsMorePages(['scan_mode' => 'quick', 'max_pages' => 10], 9) === true, 'Quick scan can analyse the 10th page');
assertTrue(ScanLimits::allowsMorePages(['scan_mode' => 'quick', 'max_pages' => 10], 10) === false, 'Quick scan stops after the configured page cap');
assertTrue(ScanLimits::percent(16, 350, 10, 'running') === 99, 'Quick-scan percent uses the page cap, not discovered URLs');
assertTrue(ScanLimits::percent(32, 350, 0, 'running') === 9, 'Full-scan percent uses discovered URLs');
assertTrue(UrlPolicy::isSafeToCrawl('https://example.test/') === true, 'Public homepage is crawlable');
assertTrue(UrlPolicy::isSafeToCrawl('https://example.test/administrator') === false, 'Administrator is not crawled');
assertTrue(UrlPolicy::isHttpUrl('viber://forward') === false, 'viber is not an HTTP URL');
assertTrue(UrlPolicy::isHttpUrl('tel:+390000') === false, 'tel is not an HTTP URL');
assertTrue(UrlPolicy::isHttpUrl('mailto:info@example.test') === false, 'mailto is not an HTTP URL');
assertTrue(UrlPolicy::isHttpUrl('whatsapp://send') === false, 'whatsapp is not an HTTP URL');
assertTrue(UrlPolicy::isHttpUrl('javascript:void(0)') === false, 'javascript URLs are rejected');
assertTrue(HtmlAnalyzer::absolutize('viber://forward', 'https://example.test/page') === null, 'viber is not resolved as a site path');
assertTrue(HtmlAnalyzer::absolutize('tel:+390000', 'https://example.test/page') === null, 'tel is not resolved as a site path');
assertTrue(UrlPolicy::redact('tel:+390123456') === 'tel:', 'Telephone numbers are not stored in URL logs');
$schemeGuard = new SsrfGuard(['example.test'], false);
$schemeLinks = HtmlAnalyzer::internalLinks(
    '<a href="viber://forward">v</a><a href="mailto:a@b.c">m</a><a href="/ok">o</a><a href="https://example.test/ok#frag">d</a>',
    'https://example.test/page',
    $schemeGuard
);
assertTrue($schemeLinks === ['https://example.test/ok'], 'Only HTTP(S) page links are queued');
$discarded = array_column(array_filter(HtmlAnalyzer::hrefRecords(
    '<a href="viber://forward">v</a>',
    'https://example.test/page',
    $schemeGuard
), static fn ($row) => !$row['ok']), 'reason');
assertTrue(\in_array('non_http_scheme', $discarded, true), 'Non-HTTP hrefs are recorded as excluded, not crawled');

$hex = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
assertTrue(CookieClassifier::suggest(['name' => $hex, 'domain' => '', 'max_age' => 31536000, 'httponly' => false, 'path' => '/'], 'example.test') === null, 'Hex cookie name with a long Max-Age is not called a Joomla session');
$sessionHit = CookieClassifier::suggest(['name' => $hex, 'domain' => 'example.test', 'max_age' => null, 'httponly' => true, 'path' => '/'], 'example.test');
assertTrue($sessionHit !== null && $sessionHit['id'] === 'joomla-session' && $sessionHit['category'] === 'necessary', 'First-party HttpOnly session hex cookie can be suggested as Joomla session');
$gcm = HtmlAnalyzer::analyze('<script id="joomcookies-gcm">gtag("consent","default");</script>', 'https://example.test/', 'example.test');
assertTrue(array_column($gcm, 'service_id') === [], 'Consent Mode stub is not reported as Google Analytics');
assertTrue(ServiceCatalog::identify('https://www.google.com/recaptcha/api.js')['category'] === 'embeds', 'Scanner and blocker use the same consent category for reCAPTCHA');
assertTrue(ServiceCatalog::identify('https://fonts.googleapis.com/css2?family=Roboto')['id'] === 'google-fonts', 'Google Fonts stylesheet host is recognised');
assertTrue(ServiceCatalog::identify('https://kit.fontawesome.com/abc.js')['id'] === 'font-awesome', 'Font Awesome kit host is recognised');

$runnerSrc = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/src/Service/Scan/ScanRunner.php') ?: '';
assertTrue(substr_count($runnerSrc, 'return $this->processBatch($scanId);') >= 1, 'scan.start processes the first batch');
assertTrue(str_contains($runnerSrc, 'reclaimStuck'), 'Stuck running queue rows are reclaimed');
assertTrue(str_contains($runnerSrc, 'reclaimAbandoned'), 'Abandoned running scans are paused instead of staying locked');
assertTrue(str_contains($runnerSrc, 'queueCount($scanId, \'done\')'), 'Page cap uses live done count, not a stale summary');
assertTrue(str_contains($runnerSrc, 'elapsed_active'), 'Active elapsed time is persisted');
assertTrue(str_contains($runnerSrc, "\$payload['busy'] = true"), 'Overlapping batches are rejected instead of duplicated');

$ctrlSrc = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/src/Controller/ScanController.php') ?: '';
assertTrue(str_contains($ctrlSrc, 'use JoomCookies\\Component\\Joomcookies\\Administrator\\Service\\Scan\\HttpCrawler'), 'ScanController imports HttpCrawler from the Scan service namespace');
assertTrue(str_contains($ctrlSrc, 'X-LiteSpeed-Cache-Control'), 'Scan JSON responses opt out of LiteSpeed cache');
assertTrue(str_contains($ctrlSrc, 'ScanAjax::wantsJson()'), 'JSON responses do not depend on Joomla InputFilter for XHR');

$js = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/media/js/scan.js') ?: '';
assertTrue(str_contains($js, "'X-Requested-With': 'XMLHttpRequest'"), 'Scan JS sends the XHR header');
assertTrue(str_contains($js, "post('scan.batch')"), 'Scan JS calls scan.batch after start');
assertTrue(str_contains($js, 'inFlight'), 'Scan JS serialises batch requests');
assertTrue(str_contains($js, 'isLooping'), 'Scan JS exposes loop state for the HTML watchdog');
assertTrue(str_contains($js, 'event.preventDefault()'), 'Native form submit is intercepted');
assertTrue(str_contains($js, "addEventListener('submit'"), 'Pause/Resume/Cancel work via form submit, not click-only handlers');

$tmpl = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/tmpl/scan/default.php') ?: '';
assertTrue(str_contains($tmpl, 'jck-scan-continue'), 'HTML fallback can POST scan.batch without fetch');
assertTrue(str_contains($tmpl, 'jck-scan-resume-form'), 'Resume is a real POST form');
assertTrue(str_contains($tmpl, 'media/com_joomcookies/js/scan.js'), 'Scan JS is loaded with an explicit media URL');
assertTrue(str_contains($tmpl, 'form.submit()'), 'Watchdog continues a running scan if fetch is unavailable');
assertTrue(!str_contains($tmpl, "<?php\n<?php"), 'Scan template does not nest PHP open tags');
assertTrue(!str_contains($runnerSrc, 'jlsitemap') && !str_contains($runnerSrc, 'com_jlsitemap'), 'Scanner does not call JL Sitemap');
assertTrue(str_contains($tmpl, 'COM_JOOMCOOKIES_SCAN_URLS'), 'Scan page lists discovered URLs');
assertTrue(str_contains($tmpl, 'name="discovery"'), 'Scan form offers a discovery method');
assertTrue(str_contains($tmpl, 'scan.urlsCsv'), 'Discovered URLs can be exported as CSV');
assertTrue(!str_contains($runnerSrc, '$root . \'/index.php?Itemid='), 'Menu seeding does not enqueue raw Itemid URLs');
assertTrue(str_contains($runnerSrc, 'complete_reason'), 'Completion reason distinguishes page cap from empty queue');
assertTrue(ScanLimits::discovery([]) === 'html', 'HTML crawl is the default discovery method');
assertTrue(ScanLimits::usesSitemap(['discovery' => 'sitemap']) && !ScanLimits::followsHtml(['discovery' => 'sitemap']), 'Sitemap mode does not follow HTML links');
assertTrue(ScanLimits::usesSitemap(['discovery' => 'combined']) && ScanLimits::followsHtml(['discovery' => 'combined']), 'Combined mode uses sitemap and HTML links');

assertTrue(UrlPolicy::normalize('https://Example.test/page/#section') === 'https://example.test/page', 'Fragments are stripped');
assertTrue(UrlPolicy::normalize('https://example.test/page/') === UrlPolicy::normalize('https://example.test/page'), 'Trailing slashes collapse except the site root');
assertTrue(UrlPolicy::normalize('https://example.test/a?utm_source=x&id=12') === 'https://example.test/a?id=12', 'Tracking parameters are dropped, content ids are kept');
assertTrue(UrlPolicy::normalize('https://example.test/a?start=20') === 'https://example.test/a?start=20', 'Pagination parameters are kept');
assertTrue(str_contains(UrlPolicy::redact('https://example.test/?token=secret'), '***'), 'Sensitive query values are redacted');
assertTrue(UrlPolicy::isSafeToCrawl('https://example.test/image.png') === false, 'Static assets are excluded');
assertTrue(UrlPolicy::isSafeToCrawl('https://example.test/index.php?print=1') === false, 'Print views are excluded');

$index = '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
    . '<sitemap><loc>https://example.test/sitemap-pages.xml</loc></sitemap>'
    . '<sitemap><loc>https://evil.test/sitemap.xml</loc></sitemap>'
    . '</sitemapindex>';
$sitemapGuard = new SsrfGuard(['example.test'], false);
$fromIndex = HtmlAnalyzer::sitemapUrls($index, $sitemapGuard);
assertTrue($fromIndex === ['https://example.test/sitemap-pages.xml'], 'Sitemap index keeps same-host loc and drops off-site URLs');

$blockedHtml = '<script type="text/plain" data-jck-managed="1" data-jck-src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>'
    . '<script type="text/plain" data-jck-src="/templates/yootheme/vendor/yootheme/theme-analytics/app/analytics.min.js"></script>'
    . '<script src="https://www.google.com/recaptcha/api.js"></script>'
    . '<div class="jck-youtube" data-jck-youtube="dQw4w9WgXcQ"></div>'
    . '<iframe data-jck-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe>'
    . '<link rel="canonical" href="https://example.test/canonical">';
$blockedFindings = HtmlAnalyzer::analyze($blockedHtml, 'https://example.test/page/', 'example.test');
$blockedIds = array_column($blockedFindings, 'service_id');
assertTrue(\in_array('gtm', $blockedIds, true), 'Blocked gtag.js is still identified');
assertTrue(\in_array('yootheme-analytics', $blockedIds, true), 'Blocked YOOtheme analytics is identified');
assertTrue(\in_array('recaptcha-api', $blockedIds, true) || \in_array('recaptcha', $blockedIds, true), 'reCAPTCHA script is identified');
assertTrue(\in_array('youtube', $blockedIds, true) || \in_array('youtube-nocookie', $blockedIds, true), 'YouTube placeholder/iframe is identified');
$gtmFinding = array_values(array_filter($blockedFindings, static fn ($row) => ($row['service_id'] ?? '') === 'gtm'))[0];
assertTrue($gtmFinding['evidence_kind'] === 'blocked' && (int) $gtmFinding['observed'] === 0, 'Prior-blocked script is not marked as an observed cookie');
assertTrue(ServiceCatalog::identify('https://www.google.com/recaptcha/api.js')['id'] === 'recaptcha-api', 'Catalogue maps reCAPTCHA to the same id as the blocker');

$redirectTransport = new class () implements HttpTransport {
    public function get(string $url, int $timeout, int $maxBytes): HttpResponse
    {
        if (str_contains($url, '/old')) {
            return new HttpResponse(301, '', ['location' => '/new?utm_source=scan'], $url);
        }

        $html = '<html><head><link rel="canonical" href="https://example.test/new"></head>'
            . '<body><a href="/dup?fbclid=1">x</a><a href="https://example.test/file.pdf">pdf</a></body></html>';

        return new HttpResponse(200, $html, ['set-cookie' => ['session=1; Path=/']], 'https://example.test/new');
    }
};
$page = (new HttpCrawler())->fetchPage(
    'https://example.test/old',
    0,
    2,
    $redirectTransport,
    new SsrfGuard(['example.test'], false),
    2,
    20000
);
assertTrue($page['url'] === 'https://example.test/new', 'Redirects are followed to the final same-host URL');
assertTrue($page['status'] === 200, 'Final HTTP status is recorded');
assertTrue($page['canonical'] === 'https://example.test/new', 'Canonical URL is recorded');
assertTrue(\in_array('https://example.test/dup', $page['html_links'], true), 'HTML links are normalised before enqueue');
assertTrue(!\in_array('https://example.test/file.pdf', $page['html_links'], true), 'Static files are not queued from HTML links');
assertTrue(array_column($page['findings'], 'type') !== [] && \in_array('cookie_header', array_column($page['findings'], 'type'), true), 'Set-Cookie on the final response is observed');

assertTrue(str_contains($ctrlSrc, 'function urlsCsv'), 'CSV export is an authorised admin task');

$policySrc = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/src/Service/Scan/UrlPolicy.php') ?: '';
assertTrue(str_contains($policySrc, '169.254') === false, 'UrlPolicy defers loopback/metadata blocking to SsrfGuard');

$sql = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/sql/install.mysql.utf8.sql') ?: '';
$updateSql = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/sql/updates/mysql/2.2.7.sql') ?: '';
assertTrue(str_contains($sql, 'joomcookies_scan_urls') && str_contains($updateSql, 'CREATE TABLE IF NOT EXISTS'), 'URL log table is added with IF NOT EXISTS for upgrades');
assertTrue(str_contains($sql, 'joomcookies_scan_queue') && str_contains($sql, 'joomcookies_scans'), 'Existing scan tables remain in the install schema');
assertTrue(str_contains($sql, 'classification_status') && str_contains($sql, 'category_suggested'), 'Install schema includes catalogue classification columns');
$update211 = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/sql/updates/mysql/2.2.11.sql') ?: '';
assertTrue(str_contains($update211, 'ADD COLUMN `classification_status`'), '2.2.11 update SQL adds catalogue columns');

$unknown = CatalogSync::draftFromFinding([
    'type' => 'script',
    'service_id' => '',
    'domain' => 'cdn.example.test',
    'category_suggested' => '',
    'target' => 'https://cdn.example.test/widget.js',
    'evidence_kind' => 'reference',
]);
assertTrue($unknown['alias'] === 'unknown-cdn-example-test', 'Unknown scripts get a stable domain alias');
assertTrue($unknown['classification_status'] === 'review' && (int) $unknown['approved'] === 0 && (int) $unknown['block_enabled'] === 0, 'Unknown services stay in review and are not auto-blocked');
$ga = CatalogSync::aliasFromFinding(['service_id' => 'ga', 'domain' => 'google-analytics.com']);
assertTrue($ga === 'ga', 'Known service_id is the stable catalogue alias');
$existing = ['approved' => 1, 'source' => 'manual', 'category' => 'marketing', 'name' => 'Keep me', 'pages' => '[]', 'classification_status' => 'manual'];
$merged = CatalogSync::mergeScan($existing, ['name' => 'Overwrite', 'category' => 'statistics', 'category_suggested' => 'statistics', 'pages' => ['https://example.test/'], 'last_scan_id' => 9, 'last_evidence' => 'reference']);
assertTrue($merged['name'] === 'Keep me' && $merged['category'] === 'marketing', 'Later scans do not overwrite approved/manual classifications');
assertTrue((int) $merged['last_scan_id'] === 9 && $merged['page_count'] === 1, 'Scan evidence still updates page coverage');

$video = HtmlAnalyzer::analyze('<video src="https://www.youtube.com/embed/dQw4w9WgXcQ"></video>', 'https://example.test/page', 'example.test');
$anomalies = array_values(array_filter($video, static fn ($row) => ($row['type'] ?? '') === 'anomaly'));
assertTrue($anomalies !== [] && !empty($anomalies[0]['meta']['malformed_video_embed']), 'YouTube /embed/ inside a video tag is flagged');
assertTrue(empty($anomalies[0]['meta']['rewritten']), 'Malformed YouTube video tags are not rewritten');
assertTrue(str_contains($runnerSrc, 'CatalogSync'), 'Completed scans upsert the service catalogue');
