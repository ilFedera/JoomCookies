<?php

declare(strict_types=1);

define('_JEXEC', 1);

spl_autoload_register(static function (string $class): void {
    $map = [
        'JoomCookies\\Plugin\\System\\Joomcookies\\' => dirname(__DIR__) . '/extensions/plg_system_joomcookies/src/',
        'JoomCookies\\Component\\Joomcookies\\Administrator\\' => dirname(__DIR__) . '/extensions/com_joomcookies/admin/src/',
    ];

    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file     = $base . $relative . '.php';

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});

use JoomCookies\Plugin\System\Joomcookies\Service\ConsentState;
use JoomCookies\Plugin\System\Joomcookies\Service\GoogleConsentMode;
use JoomCookies\Plugin\System\Joomcookies\Service\HtmlGuard;
use JoomCookies\Plugin\System\Joomcookies\Service\ServiceRegistry;
use JoomCookies\Plugin\System\Joomcookies\Service\YoutubeEmbed;

$failures = 0;

function assertTrue(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo "OK  {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL {$message}\n";
}

$denied  = ConsentState::deniedOptional();
$encoded = ConsentState::encode(['statistics' => true, 'necessary' => false], '2', 1700000000);
$decoded = ConsentState::decode($encoded);

assertTrue($decoded !== null, 'Consent payload decodes');
assertTrue($decoded['v'] === '2', 'Consent version is stored');
assertTrue($decoded['t'] === 1700000000, 'Consent timestamp is stored');
assertTrue($decoded['c']['necessary'] === true, 'Necessary category cannot be disabled');
assertTrue($decoded['c']['statistics'] === true, 'Statistics can be enabled');
assertTrue($decoded['c']['marketing'] === false, 'Marketing stays off unless chosen');
assertTrue(ConsentState::matchesVersion($decoded, '2'), 'Matching version is accepted');
assertTrue(!ConsentState::matchesVersion($decoded, '3'), 'Changed policy version invalidates consent');
assertTrue(ConsentState::decode('<script>alert(1)</script>') === null, 'Invalid consent payload is rejected');
assertTrue(ConsentState::allows($denied, 'necessary'), 'Necessary allowed without choice');
assertTrue(!ConsentState::allows($denied, 'statistics'), 'Statistics blocked by default');

assertTrue(YoutubeEmbed::videoId('dQw4w9WgXcQ') === 'dQw4w9WgXcQ', 'Plain YouTube id');
assertTrue(YoutubeEmbed::videoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ') === 'dQw4w9WgXcQ', 'Watch URL');
assertTrue(YoutubeEmbed::videoId('https://youtu.be/dQw4w9WgXcQ') === 'dQw4w9WgXcQ', 'Short URL');
assertTrue(YoutubeEmbed::videoId('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ') === 'dQw4w9WgXcQ', 'Embed URL');
assertTrue(YoutubeEmbed::videoId('https://youtu.be/dQw4w9WgXcQ?t=12') === 'dQw4w9WgXcQ', 'Short URL with extra path data');
assertTrue(YoutubeEmbed::videoId('youtube.com/watch?v=dQw4w9WgXcQ') === 'dQw4w9WgXcQ', 'Scheme-less watch URL');
assertTrue(YoutubeEmbed::videoId('https://evil.example/dQw4w9WgXcQ') === null, 'Unknown host rejected');
assertTrue(YoutubeEmbed::videoId('not-an-id') === null, 'Invalid id rejected');
assertTrue(str_contains(YoutubeEmbed::playerSrc('dQw4w9WgXcQ', true), 'youtube-nocookie.com'), 'Privacy host used');
assertTrue(preg_match(YoutubeEmbed::CF_SHORTCODE_PATTERN, '{cfyoutube dQw4w9WgXcQ}') === 1, 'cfyoutube shortcode accepts a valid YouTube ID');

$registry = new ServiceRegistry([
    [
        'id' => 'custom-stat',
        'name' => 'Custom stats',
        'category' => 'statistics',
        'match_type' => 'domain',
        'match_value' => 'stats.example.test',
        'cookies' => 'ex_stat',
    ],
]);

$html = <<<HTML
<html><body>
<script src="/media/system/js/core.min.js"></script>
<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>
<script>gtag('config','G-TEST');</script>
<script src="https://stats.example.test/tracker.js"></script>
<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Demo &amp; Video"></iframe>
<iframe src="https://example.com/safe"></iframe>
{joomcookies youtube="dQw4w9WgXcQ" title="Article video"}
<div class="youtube-player" data-id="dQw4w9WgXcQ"></div>
{joomcookies-preferences}
<script id="safe-inline">window.siteReady = true;</script>
</body></html>
HTML;

$guard = new HtmlGuard($registry, [
    'youtube_nocookie' => true,
    'youtube_thumbnails' => 'local',
    'open_prefs_label' => 'Prefs',
    'embed_blocked_label' => 'Blocked',
    'embed_load_label' => 'Load',
    'youtube_blocked_label' => 'YouTube video',
    'youtube_load_label' => 'Load video',
    'youtube_help' => 'Help',
], $denied);

$blocked = $guard->process($html);

assertTrue(str_contains($blocked, '/media/system/js/core.min.js'), 'Joomla core script remains executable');
assertTrue(preg_match('#<script[^>]*\ssrc=["\']https://www\.googletagmanager\.com#i', $blocked) !== 1, 'GTM src is not left executable');
assertTrue(str_contains($blocked, 'type="text/plain"') && str_contains($blocked, 'data-jck-src="https://www.googletagmanager.com/gtag/js?id=G-TEST"'), 'GTM converted to text/plain');
assertTrue(str_contains($blocked, 'data-jck-src="https://stats.example.test/tracker.js"'), 'Custom service is blocked');
assertTrue(str_contains($blocked, "gtag('config','G-TEST')") && str_contains($blocked, 'data-jck-category="statistics"'), 'Inline gtag is blocked');
assertTrue(!preg_match('#<iframe[^>]+src="https://www.youtube.com/embed/#', $blocked), 'YouTube iframe is not present before consent');
assertTrue(str_contains($blocked, 'data-jck-youtube="dQw4w9WgXcQ"'), 'YouTube placeholder is rendered');
assertTrue(str_contains($blocked, 'data-jck-open-prefs="1"'), 'YouTube placeholder can open preferences');
assertTrue(str_contains($blocked, 'data-jck-yt="1"') && str_contains($blocked, 'youtube-player'), 'Existing youtube-player containers are enhanced');
$nestedPlayer = $guard->process('<div class="youtube-player" data-id="dQw4w9WgXcQ"><div class="uk-cover">keep</div></div>');
assertTrue(str_contains($nestedPlayer, 'uk-cover') && !str_contains($nestedPlayer, 'data-jck-yt="1"'), 'Nested youtube-player markup is left for JS, not split by PHP');
assertTrue(!str_contains($blocked, 'i.ytimg.com'), 'YouTube placeholders do not request thumbnails');
assertTrue(str_contains($blocked, 'https://example.com/safe'), 'Unregistered iframe is left untouched');
assertTrue(str_contains($blocked, 'data-jck-open-prefs="1"'), 'Preferences shortcode becomes a button');
assertTrue(str_contains($blocked, 'Demo &amp; Video') || str_contains($blocked, 'Demo & Video'), 'Embed title is escaped');
assertTrue(!str_contains($blocked, 'background-image:url(\'https://i.ytimg.com'), 'Local thumbnails do not contact i.ytimg.com');
assertTrue(preg_match('#<script id="safe-inline">window.siteReady = true;</script>#', $blocked) === 1, 'Unmatched inline script remains executable');

$allowed = (new HtmlGuard($registry, [
    'youtube_nocookie' => true,
    'youtube_thumbnails' => 'local',
    'open_prefs_label' => 'Prefs',
    'embed_blocked_label' => 'Blocked',
    'embed_load_label' => 'Load',
    'youtube_blocked_label' => 'YouTube video',
    'youtube_load_label' => 'Load video',
    'youtube_help' => 'Help',
], ['necessary' => true, 'statistics' => true, 'marketing' => true, 'embeds' => true]))->process($html);

assertTrue(str_contains($allowed, 'type="text/plain"') && str_contains($allowed, 'data-jck-src="https://www.googletagmanager.com/gtag/js?id=G-TEST"'), 'HTML stays blocked even if a consent cookie is present (LiteSpeed-safe)');
assertTrue(!preg_match('#<iframe[^>]+src="https://www\.youtube#', $allowed), 'Live YouTube iframes are not written into cacheable HTML');
assertTrue(str_contains($allowed, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'), 'Placeholder payload still uses the nocookie host');
assertTrue($allowed === $blocked, 'Consent cookie does not change public HTML, so one cache copy is safe');

$yooSrc = '/templates/yootheme/vendor/yootheme/theme-analytics/app/analytics.min.js';
$yooMedia = '/media/templates/site/yootheme/vendor/yootheme/theme-analytics/app/analytics.min.js';
$yooHit = $registry->matchScript($yooSrc, '');
assertTrue($yooHit !== null && $yooHit['id'] === 'yootheme-analytics', 'YOOtheme analytics URL is classified as statistics');
assertTrue($registry->matchScript($yooMedia, '')['id'] === 'yootheme-analytics', 'YOOtheme analytics under media/templates is classified');
assertTrue($registry->matchScript('https://www.google.com/recaptcha/api.js', '')['category'] === 'embeds', 'reCAPTCHA API is consent-managed');
assertTrue($registry->matchScript('https://www.gstatic.com/recaptcha/releases/test/recaptcha__it.js', '')['category'] === 'embeds', 'reCAPTCHA runtime is consent-managed');
assertTrue($registry->matchScript('/media/com_convertforms/js/recaptcha_v3.js?123', '')['category'] === 'embeds', 'Convert Forms reCAPTCHA adapter is consent-managed');
$suppressed = new ServiceRegistry([], ['youtube']);
assertTrue($suppressed->matchFrame('https://www.youtube.com/embed/dQw4w9WgXcQ') === null, 'Disabled catalogue row suppresses the built-in YouTube rule');
$siteOverride = new ServiceRegistry([['id' => 'custom-captcha', 'name' => 'Override', 'category' => 'marketing', 'match_type' => 'url_contains', 'match_value' => '/recaptcha/api.js', 'cookies' => '']]);
assertTrue($siteOverride->matchScript('https://www.google.com/recaptcha/api.js', '')['id'] === 'custom-captcha', 'Approved site rule takes precedence');
assertTrue($registry->matchScript('/media/templates/site/cassiopeia/js/template.min.js', '') === null, 'Ordinary template JS is not classified');

$yooPage = <<<HTML
<html><head>
<script src="{$yooSrc}"></script>
<script src="{$yooMedia}"></script>
<script src="/media/templates/site/cassiopeia/js/template.min.js"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script>(function(){o("https://www.googletagmanager.com/gtag/js?id="+t)})();</script>
</head><body></body></html>
HTML;
$yooBlocked = $guard->process($yooPage);
assertTrue(str_contains($yooBlocked, 'data-jck-src="' . $yooSrc . '"') && str_contains($yooBlocked, 'data-jck-service="yootheme-analytics"'), 'YOOtheme analytics.min.js is prior-blocked');
assertTrue(str_contains($yooBlocked, 'data-jck-src="' . $yooMedia . '"'), 'theme-analytics in media/templates is prior-blocked');
assertTrue(preg_match('#<script src="/media/templates/site/cassiopeia/js/template.min.js"></script>#', $yooBlocked) === 1, 'Unrelated template JS stays executable');
assertTrue(str_contains($yooBlocked, 'data-jck-src="https://www.google.com/recaptcha/api.js"') && str_contains($yooBlocked, 'data-jck-service="recaptcha-api"'), 'reCAPTCHA blocked before consent');
$convertFormsBlocked = $guard->process('<script src="/media/com_convertforms/js/recaptcha_v3.js?abc"></script>');
assertTrue(str_contains($convertFormsBlocked, 'data-jck-service="recaptcha-convertforms"') && str_contains($convertFormsBlocked, 'type="text/plain"'), 'Convert Forms reCAPTCHA adapter blocked');
assertTrue(!str_contains($convertFormsBlocked, 'class="jck-consent-hint"'), 'Unidentified reCAPTCHA integration does not append a global visible hint');
assertTrue(!str_contains($convertFormsBlocked, 'jck-embed'), 'reCAPTCHA hint does not reuse the YouTube 16:9 embed box');
assertTrue(!str_contains($convertFormsBlocked, '<video'), 'HtmlGuard does not invent video-tag rewrites');
$cfForm = $guard->process('<form class="convertforms" method="post"><input type="hidden" name="' . htmlspecialchars('dummy') . '" value="1"><script src="/media/com_convertforms/js/recaptcha_v3.js"></script></form>');
assertTrue(preg_match('#<form class="convertforms"[^>]*>\\s*<div class="jck-consent-hint"#', $cfForm) === 1, 'Consent hint is inserted inside the Convert Forms form without replacing it');
$twoCfForms = $guard->process('<form class="convertforms"></form><form id="cf-form-second"></form><script src="/media/com_convertforms/js/recaptcha_v3.js"></script>');
assertTrue(substr_count($twoCfForms, 'data-jck-consent-hint="embeds"') === 2, 'Each identified Convert Forms form receives its own local hint');
$headBlocked = $guard->process('<html><head><script src="https://www.google.com/recaptcha/api.js"></script></head><body><p>Contact</p></body></html>');
assertTrue(!preg_match('#<head>.*jck-consent-hint.*</head>#s', $headBlocked), 'Consent hint is not injected into HEAD');
assertTrue(!str_contains($headBlocked, 'jck-consent-hint'), 'Head-only reCAPTCHA does not get an unsafe global body hint');
$frontendJs = file_get_contents(dirname(__DIR__) . '/extensions/plg_system_joomcookies/media/js/joomcookies.js') ?: '';
assertTrue(str_contains($frontendJs, 'syncConsentHints'), 'Consent hints hide after External content is allowed');
assertTrue(str_contains($yooBlocked, 'data-jck-category="statistics"') && str_contains($yooBlocked, 'googletagmanager.com/gtag/js'), 'Inline gtag/js loader is blocked');
assertTrue(!preg_match('#<script src="' . preg_quote($yooSrc, '#') . '"></script>#', $yooBlocked), 'YOOtheme analytics src is not left executable');

$gcmHtml = GoogleConsentMode::injectAfterHeadOpen($yooBlocked);
assertTrue(str_contains($gcmHtml, 'id="joomcookies-gcm"') && str_contains($gcmHtml, "analytics_storage:'denied'"), 'Consent Mode default is denied and cache-safe');
assertTrue(!str_contains($gcmHtml, "analytics_storage:'granted'"), 'Cached HTML never grants analytics storage');
assertTrue(GoogleConsentMode::injectAfterHeadOpen($gcmHtml) === $gcmHtml, 'Consent Mode snippet is injected once');
$gcmProcessed = $guard->process($gcmHtml);
assertTrue(str_contains($gcmProcessed, 'id="joomcookies-gcm"') && str_contains($gcmProcessed, "gtag('consent','default'"), 'Consent Mode stub is not rewritten by HtmlGuard');

$xss = (new HtmlGuard($registry, [
    'youtube_nocookie' => true,
    'youtube_thumbnails' => 'local',
    'youtube_load_label' => 'Load',
    'youtube_blocked_label' => 'Video',
], $denied))->replaceShortcodes('{joomcookies youtube="dQw4w9WgXcQ" title="<img src=x onerror=alert(1)>"}');

assertTrue(!str_contains($xss, '<img src=x'), 'Shortcode title is escaped');
$cfShortcode = $guard->replaceShortcodes('{cfyoutube dQw4w9WgXcQ}');
assertTrue(str_contains($cfShortcode, 'class="cf-youtube"') && str_contains($cfShortcode, 'data-video-id="dQw4w9WgXcQ"'), 'cfyoutube shortcode produces the Grid-compatible local component');

$roots = [
    dirname(__DIR__) . '/extensions',
    dirname(__DIR__) . '/package',
];
$hits = [];

foreach ($roots as $source) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (!$file->isFile() || preg_match('/\.(php|js|css|xml|ini|json)$/i', $file->getPathname()) !== 1) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if ($contents !== false && preg_match('/clickandfly|cdn\.cookieyes|cookieyes\.com\/client|gtm\.js\?id=GTM-[A-Z0-9]+/i', $contents)) {
            $hits[] = $file->getPathname();
        }
    }
}

assertTrue($hits === [], 'No site-specific or CookieYes CDN bindings in source');

require __DIR__ . '/v2.php';
require __DIR__ . '/scan.php';
require __DIR__ . '/lang.php';

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} checks failed.\n");
    exit(1);
}

echo "\nAll checks passed.\n";
exit(0);
