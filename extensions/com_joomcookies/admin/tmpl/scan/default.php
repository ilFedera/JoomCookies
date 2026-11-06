<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$page = $this->page;
$stats = $page['stats'] ?? [];
$progress = $page['progress'] ?? [];
$status = (string) ($progress['status'] ?? ($page['current']['status'] ?? ''));
$scanId = (int) ($page['current']['id'] ?? 0);
$action = Route::_('index.php?option=com_joomcookies');
include dirname(__DIR__) . '/_nav.php';
?>
<div id="jck-scan-error" class="alert alert-danger" hidden></div>
<form action="<?php echo $action; ?>" method="post" class="mb-4" id="jck-scan-run" data-error-http="<?php echo $this->escape(Text::_('COM_JOOMCOOKIES_SCAN_AJAX_ERROR')); ?>">
	<div class="mb-3">
		<label class="form-label" for="start_url"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_START_URL'); ?></label>
		<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_START_URL_DESC'); ?></div>
		<input class="form-control" type="url" name="start_url" id="start_url" value="<?php echo $this->escape((string) $page['start_url']); ?>">
	</div>
	<div class="mb-3">
		<label class="form-label" for="scan_mode"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MODE'); ?></label>
		<select class="form-select" name="scan_mode" id="scan_mode">
			<option value="quick"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MODE_QUICK'); ?></option>
			<option value="complete"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MODE_COMPLETE'); ?></option>
		</select>
		<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MODE_DESC'); ?></div>
	</div>
	<div class="mb-3">
		<label class="form-label" for="discovery"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY'); ?></label>
		<select class="form-select" name="discovery" id="discovery">
			<?php $discovery = (string) ($page['limits']['discovery'] ?? 'html'); ?>
			<option value="html"<?php echo $discovery === 'html' ? ' selected' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY_HTML'); ?></option>
			<option value="sitemap"<?php echo $discovery === 'sitemap' ? ' selected' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY_SITEMAP'); ?></option>
			<option value="combined"<?php echo $discovery === 'combined' ? ' selected' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY_COMBINED'); ?></option>
		</select>
		<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY_DESC'); ?></div>
	</div>
	<p class="text-muted"><?php echo Text::sprintf('COM_JOOMCOOKIES_SCAN_LIMITS', (int) $page['limits']['max_pages'], (int) $page['limits']['max_depth'], (int) $page['limits']['max_seconds']); ?></p>
	<input type="hidden" name="task" value="scan.start">
	<input type="hidden" name="scan_id" value="<?php echo $scanId; ?>">
	<div class="d-flex flex-wrap gap-2 mb-3">
		<button class="btn btn-primary" type="submit" id="jck-scan-start"<?php echo $status === 'running' ? ' disabled' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_START'); ?></button>
	</div>
	<?php echo $this->token; ?>
</form>
<div class="d-flex flex-wrap gap-2 mb-4">
	<form action="<?php echo $action; ?>" method="post" id="jck-scan-pause-form">
		<input type="hidden" name="task" value="scan.pause">
		<input type="hidden" name="scan_id" value="<?php echo $scanId; ?>">
		<?php echo $this->token; ?>
		<button class="btn btn-secondary" type="submit" id="jck-scan-pause"<?php echo $status !== 'running' ? ' disabled' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_PAUSE'); ?></button>
	</form>
	<form action="<?php echo $action; ?>" method="post" id="jck-scan-resume-form">
		<input type="hidden" name="task" value="scan.resume">
		<input type="hidden" name="scan_id" value="<?php echo $scanId; ?>">
		<?php echo $this->token; ?>
		<button class="btn btn-secondary" type="submit" id="jck-scan-resume"<?php echo $status !== 'paused' ? ' disabled' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_RESUME'); ?></button>
	</form>
	<form action="<?php echo $action; ?>" method="post" id="jck-scan-cancel-form">
		<input type="hidden" name="task" value="scan.cancel">
		<input type="hidden" name="scan_id" value="<?php echo $scanId; ?>">
		<?php echo $this->token; ?>
		<button class="btn btn-danger" type="submit" id="jck-scan-cancel"<?php echo !in_array($status, ['running', 'paused'], true) ? ' disabled' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_CANCEL'); ?></button>
	</form>
</div>
<form action="<?php echo $action; ?>" method="post" id="jck-scan-continue" hidden>
	<input type="hidden" name="task" value="scan.batch">
	<input type="hidden" name="scan_id" value="<?php echo $scanId; ?>">
	<?php echo $this->token; ?>
</form>
<div class="mb-4">
	<div class="d-flex justify-content-between"><span><?php echo Text::_('COM_JOOMCOOKIES_SCAN_STATUS'); ?>: <strong id="jck-scan-status"><?php echo $this->escape((string) ($progress['status'] ?? ($page['current']['status'] ?? '-'))); ?></strong></span><span id="jck-scan-percent"><?php echo (int) ($progress['percent_final'] ?? $progress['percent'] ?? 0); ?>%</span></div>
	<div class="small text-muted mt-1" id="jck-scan-complete-reason">
		<?php
		$reason = (string) ($progress['complete_reason'] ?? '');
		if ($reason === 'page_cap') {
			echo Text::_('COM_JOOMCOOKIES_SCAN_COMPLETE_CAP');
		} elseif ($reason === 'queue_empty') {
			echo Text::_('COM_JOOMCOOKIES_SCAN_COMPLETE_QUEUE');
		}
		?>
	</div>
	<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) ($progress['percent'] ?? 0); ?>">
		<div class="progress-bar" id="jck-scan-progress" style="width: <?php echo (int) ($progress['percent'] ?? 0); ?>%"></div>
	</div>
	<div class="row g-2 mt-2 small">
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERED'); ?>: <span id="jck-scan-discovered"><?php echo (int) ($progress['discovered'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_PAGES'); ?>: <span id="jck-scan-pages"><?php echo (int) ($progress['pages'] ?? $stats['pages'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_PENDING'); ?>: <span id="jck-scan-pending"><?php echo (int) ($progress['pending'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_ERRORS'); ?>: <span id="jck-scan-errors"><?php echo (int) ($progress['errors'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_EXCLUDED'); ?>: <span id="jck-scan-excluded"><?php echo (int) ($progress['excluded'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_ELAPSED'); ?>: <span id="jck-scan-elapsed"><?php echo (int) ($progress['elapsed_active'] ?? $progress['elapsed'] ?? 0); ?>s</span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_TOTAL'); ?>: <span id="jck-scan-findings"><?php echo (int) ($progress['findings'] ?? $stats['total'] ?? 0); ?></span></div>
	</div>
</div>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="mb-4">
	<input type="hidden" name="task" value="config.save">
	<input type="hidden" name="return_view" value="scan">
	<div class="row g-2">
		<div class="col-md-4">
			<label class="form-label" for="scan_max_pages"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_PAGES'); ?></label>
			<input class="form-control" id="scan_max_pages" type="number" name="jform[scan_max_pages]" value="<?php echo (int) $page['limits']['max_pages']; ?>" min="1" max="5000">
			<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_PAGES_DESC'); ?></div>
		</div>
		<div class="col-md-4">
			<label class="form-label" for="scan_max_depth"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_DEPTH'); ?></label>
			<input class="form-control" id="scan_max_depth" type="number" name="jform[scan_max_depth]" value="<?php echo (int) $page['limits']['max_depth']; ?>" min="0" max="8">
		</div>
		<div class="col-md-3">
			<label class="form-label" for="scan_max_seconds"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_SECONDS'); ?></label>
			<input class="form-control" id="scan_max_seconds" type="number" name="jform[scan_max_seconds]" value="<?php echo (int) $page['limits']['max_seconds']; ?>" min="3" max="25">
			<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_SECONDS_DESC'); ?></div>
		</div>
		<div class="col-md-3">
			<label class="form-label" for="scan_discovery"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY'); ?></label>
			<select class="form-select" id="scan_discovery" name="jform[scan_discovery]">
				<option value="html"<?php echo ($page['limits']['discovery'] ?? '') === 'html' ? ' selected' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY_HTML'); ?></option>
				<option value="sitemap"<?php echo ($page['limits']['discovery'] ?? '') === 'sitemap' ? ' selected' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY_SITEMAP'); ?></option>
				<option value="combined"<?php echo ($page['limits']['discovery'] ?? '') === 'combined' ? ' selected' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERY_COMBINED'); ?></option>
			</select>
		</div>
	</div>
	<button class="btn btn-secondary mt-2" type="submit"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_SAVE_LIMITS'); ?></button>
	<?php echo $this->token; ?>
</form>
<div class="alert alert-info">
	<?php echo Text::_('COM_JOOMCOOKIES_SCAN_BROWSER_UNAVAILABLE'); ?>
</div>
<?php if (!empty($page['current'])) : ?>
	<div class="row g-3 mb-4">
		<?php
		$cards = [
			'pages'            => 'COM_JOOMCOOKIES_SCAN_PAGES',
			'cookie_observed'  => 'COM_JOOMCOOKIES_SCAN_COOKIES_OBSERVED',
			'cookie_browser'   => 'COM_JOOMCOOKIES_SCAN_COOKIES_BROWSER',
			'cookie_reference' => 'COM_JOOMCOOKIES_SCAN_COOKIES_REFERENCE',
			'script'           => 'COM_JOOMCOOKIES_SCAN_SCRIPTS',
			'iframe'           => 'COM_JOOMCOOKIES_SCAN_IFRAMES',
			'known'            => 'COM_JOOMCOOKIES_SCAN_KNOWN',
			'unknown'          => 'COM_JOOMCOOKIES_SCAN_UNKNOWN',
			'total'            => 'COM_JOOMCOOKIES_SCAN_TOTAL',
		];
		?>
		<?php foreach ($cards as $key => $label) : ?>
			<div class="col-md-3">
				<div class="card">
					<div class="card-body">
						<div class="text-muted"><?php echo Text::_($label); ?></div>
						<div class="fs-4"><?php echo (int) ($stats[$key] ?? 0); ?></div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php if (!empty($page['diff'])) : ?>
	<div class="alert alert-info">
		<?php echo Text::sprintf('COM_JOOMCOOKIES_SCAN_DIFF', \count($page['diff']['added']), \count($page['diff']['removed']), (int) $page['diff']['unchanged']); ?>
		<?php if (!empty($page['diff']['incomplete'])) : ?>
			<div><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DIFF_INCOMPLETE'); ?></div>
		<?php endif; ?>
	</div>
<?php endif; ?>
<h2 class="h5"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_RESULTS'); ?></h2>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_TYPE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_NAME'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_DOMAIN'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_EVIDENCE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_CATEGORY'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_PAGE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_OCCURRENCES'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($page['findings'] as $row) : ?>
			<tr>
				<td><?php echo $this->escape((string) $row['type']); ?></td>
				<td><?php echo $this->escape((string) $row['name']); ?></td>
				<td><?php echo $this->escape((string) $row['domain']); ?></td>
				<td><?php
				$kind = (string) ($row['evidence_kind'] ?? '');
				if ((int) $row['observed'] === 1) {
					echo Text::_('COM_JOOMCOOKIES_OBSERVED');
				} elseif ($kind === 'blocked') {
					echo Text::_('COM_JOOMCOOKIES_SCAN_EVIDENCE_BLOCKED');
				} else {
					echo Text::_('COM_JOOMCOOKIES_REFERENCE');
				}
				?></td>
				<td><?php echo $this->escape((string) $row['category_suggested']); ?></td>
				<td><?php echo $this->escape((string) ($row['page_url'] ?? '')); ?><?php echo !empty($row['page_count']) && (int) $row['page_count'] > 1 ? ' (+' . ((int) $row['page_count'] - 1) . ')' : ''; ?></td>
				<td><?php echo (int) ($row['occurrences'] ?? 1); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php
$urls = $page['urls'] ?? ['rows' => [], 'total' => 0];
$filters = $page['url_filters'] ?? ['q' => '', 'outcome' => '', 'discovery' => '', 'limit' => 25, 'start' => 0];
$totalUrls = (int) ($urls['total'] ?? 0);
$limit = (int) ($filters['limit'] ?? 25);
$start = (int) ($filters['start'] ?? 0);
?>
<h2 class="h5 mt-4"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URLS'); ?></h2>
<p class="text-muted"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URLS_DESC'); ?></p>
<form action="<?php echo Route::_('index.php?option=com_joomcookies&view=scan&id=' . $scanId); ?>" method="get" class="row g-2 mb-3">
	<input type="hidden" name="option" value="com_joomcookies">
	<input type="hidden" name="view" value="scan">
	<input type="hidden" name="id" value="<?php echo $scanId; ?>">
	<div class="col-md-4">
		<input class="form-control" type="search" name="url_q" value="<?php echo $this->escape((string) $filters['q']); ?>" placeholder="<?php echo $this->escape(Text::_('COM_JOOMCOOKIES_SCAN_URLS_SEARCH')); ?>">
	</div>
	<div class="col-md-2">
		<select class="form-select" name="url_outcome">
			<option value=""><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URLS_ALL_OUTCOMES'); ?></option>
			<?php
			$outcomes = [
				'analyzed'  => 'COM_JOOMCOOKIES_SCAN_OUTCOME_ANALYZED',
				'queued'    => 'COM_JOOMCOOKIES_SCAN_OUTCOME_QUEUED',
				'duplicate' => 'COM_JOOMCOOKIES_SCAN_OUTCOME_DUPLICATE',
				'excluded'  => 'COM_JOOMCOOKIES_SCAN_OUTCOME_EXCLUDED',
				'error'     => 'COM_JOOMCOOKIES_SCAN_OUTCOME_ERROR',
			];
			?>
			<?php foreach ($outcomes as $outcome => $outcomeLabel) : ?>
				<option value="<?php echo $outcome; ?>"<?php echo ($filters['outcome'] ?? '') === $outcome ? ' selected' : ''; ?>><?php echo Text::_($outcomeLabel); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="col-md-2">
		<select class="form-select" name="url_discovery">
			<option value=""><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URLS_ALL_DISCOVERY'); ?></option>
			<?php foreach (['seed', 'html_link', 'sitemap', 'menu', 'canonical'] as $method) : ?>
				<option value="<?php echo $method; ?>"<?php echo ($filters['discovery'] ?? '') === $method ? ' selected' : ''; ?>><?php echo $this->escape($method); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="col-md-2">
		<button class="btn btn-secondary" type="submit"><?php echo Text::_('JSEARCH_FILTER'); ?></button>
	</div>
</form>
<?php if ($scanId > 0) : ?>
<form action="<?php echo $action; ?>" method="post" class="mb-3">
	<input type="hidden" name="task" value="scan.urlsCsv">
	<input type="hidden" name="scan_id" value="<?php echo $scanId; ?>">
	<button class="btn btn-outline-secondary btn-sm" type="submit"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URLS_CSV'); ?></button>
	<?php echo $this->token; ?>
</form>
<?php endif; ?>
<div class="table-responsive">
	<table class="table table-sm">
		<thead>
			<tr>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_ORIGINAL'); ?></th>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_NORMALIZED'); ?></th>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_REFERRER'); ?></th>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_DISCOVERY'); ?></th>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_STATUS'); ?></th>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_FINAL'); ?></th>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_OUTCOME'); ?></th>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_REASON'); ?></th>
				<th><?php echo Text::_('COM_JOOMCOOKIES_SCAN_URL_CANONICAL'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach (($urls['rows'] ?? []) as $urlRow) : ?>
				<tr>
					<td><?php echo $this->escape((string) ($urlRow['original_url'] ?? '')); ?></td>
					<td><?php echo $this->escape((string) ($urlRow['normalized'] ?? '')); ?></td>
					<td><?php echo $this->escape((string) ($urlRow['referrer_url'] ?? '')); ?></td>
					<td><?php echo $this->escape((string) ($urlRow['discovery'] ?? '')); ?></td>
					<td><?php echo (int) ($urlRow['http_status'] ?? 0); ?></td>
					<td><?php echo $this->escape((string) ($urlRow['final_url'] ?? '')); ?></td>
					<td><?php echo $this->escape((string) ($urlRow['outcome'] ?? '')); ?></td>
					<td><?php echo $this->escape((string) ($urlRow['reason'] ?? '')); ?></td>
					<td><?php echo $this->escape((string) ($urlRow['canonical_url'] ?? '')); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php if ($totalUrls > $limit) : ?>
	<nav class="mb-4">
		<?php if ($start > 0) : ?>
			<a class="btn btn-sm btn-secondary" href="<?php echo Route::_('index.php?option=com_joomcookies&view=scan&id=' . $scanId . '&url_q=' . rawurlencode((string) $filters['q']) . '&url_outcome=' . rawurlencode((string) $filters['outcome']) . '&url_discovery=' . rawurlencode((string) $filters['discovery']) . '&url_limit=' . $limit . '&url_start=' . max(0, $start - $limit)); ?>"><?php echo Text::_('JPREV'); ?></a>
		<?php endif; ?>
		<?php if (($start + $limit) < $totalUrls) : ?>
			<a class="btn btn-sm btn-secondary" href="<?php echo Route::_('index.php?option=com_joomcookies&view=scan&id=' . $scanId . '&url_q=' . rawurlencode((string) $filters['q']) . '&url_outcome=' . rawurlencode((string) $filters['outcome']) . '&url_discovery=' . rawurlencode((string) $filters['discovery']) . '&url_limit=' . $limit . '&url_start=' . ($start + $limit)); ?>"><?php echo Text::_('JNEXT'); ?></a>
		<?php endif; ?>
		<span class="ms-2 small text-muted"><?php echo ($start + 1); ?>–<?php echo min($totalUrls, $start + $limit); ?> / <?php echo $totalUrls; ?></span>
	</nav>
<?php endif; ?>
<?php
$scanJs = rtrim(Uri::root(true), '/') . '/media/com_joomcookies/js/scan.js?v=2.2.9';
$wa = $this->getDocument()->getWebAssetManager();
try {
	$wa->getRegistry()->addExtensionRegistryFile('com_joomcookies');
	$wa->useScript('com_joomcookies.scan');
} catch (\Throwable) {
	$wa->registerAndUseScript(
		'com_joomcookies.scan',
		'com_joomcookies/js/scan.js',
		['version' => 'auto', 'relative' => true],
		['defer' => true]
	);
}
?>
<script src="<?php echo $this->escape($scanJs); ?>" id="com-joomcookies-scan-js"></script>
<?php if ($status === 'running') : ?>
<script>
(function () {
  window.setTimeout(function () {
    if (window.JoomCookiesScan && window.JoomCookiesScan.isLooping && window.JoomCookiesScan.isLooping()) {
      return;
    }
    var form = document.getElementById('jck-scan-continue');
    if (form) {
      form.submit();
    }
  }, 2000);
})();
</script>
<?php endif; ?>
