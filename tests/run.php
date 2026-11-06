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
assertTrue(YoutubeEmbed::videoId('https://evil.example/dQw4w9WgXcQ') === null, 'Unknown host rejected');
assertTrue(YoutubeEmbed::videoId('not-an-id') === null, 'Invalid id rejected');
assertTrue(str_contains(YoutubeEmbed::playerSrc('dQw4w9WgXcQ', true), 'youtube-nocookie.com'), 'Privacy host used');

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
assertTrue(str_contains($blocked, 'https://example.com/safe'), 'Unregistered iframe is left untouched');
assertTrue(str_contains($blocked, 'data-jck-open-prefs="1"'), 'Preferences shortcode becomes a button');
assertTrue(str_contains($blocked, 'Demo &amp; Video') || str_contains($blocked, 'Demo & Video'), 'Embed title is escaped');
assertTrue(!str_contains($blocked, 'background-image:url(\'https://i.ytimg.com'), 'Local thumbnails do not contact i.ytimg.com');
assertTrue(preg_match('#<script id="safe-inline">window.siteReady = true;</script>#', $blocked) === 1, 'Unmatched inline script remains executable');

$allowed = (new HtmlGuard($registry, [
    'youtube_nocookie' => true,
    'youtube_thumbnails' => 'local',
    'open_prefs_label' => 'Prefs',
    'youtube_load_label' => 'Load video',
], ['necessary' => true, 'statistics' => true, 'marketing' => true, 'embeds' => true]))->process($html);

assertTrue(str_contains($allowed, 'src="https://www.googletagmanager.com/gtag/js?id=G-TEST"'), 'Accepted statistics scripts stay active');
assertTrue(str_contains($allowed, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'), 'Accepted YouTube uses nocookie host');

$xss = (new HtmlGuard($registry, [
    'youtube_nocookie' => true,
    'youtube_thumbnails' => 'local',
    'youtube_load_label' => 'Load',
    'youtube_blocked_label' => 'Video',
], $denied))->replaceShortcodes('{joomcookies youtube="dQw4w9WgXcQ" title="<img src=x onerror=alert(1)>"}');

assertTrue(!str_contains($xss, '<img src=x'), 'Shortcode title is escaped');

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

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} checks failed.\n");
    exit(1);
}

echo "\nAll checks passed.\n";
exit(0);
