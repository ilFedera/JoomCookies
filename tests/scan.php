<?php

declare(strict_types=1);

use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanAjax;
use JoomCookies\Component\Joomcookies\Administrator\Service\Scan\ScanLimits;
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

$runnerSrc = file_get_contents(dirname(__DIR__) . '/extensions/com_joomcookies/admin/src/Service/Scan/ScanRunner.php') ?: '';
assertTrue(substr_count($runnerSrc, 'return $this->processBatch($scanId);') >= 1, 'scan.start processes the first batch');
assertTrue(str_contains($runnerSrc, 'reclaimStuck'), 'Stuck running queue rows are reclaimed');
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
