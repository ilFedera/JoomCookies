<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$page = $this->page;
$stats = $page['stats'] ?? [];
$progress = $page['progress'] ?? [];
include dirname(__DIR__) . '/_nav.php';
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="mb-4" id="jck-scan-run">
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
	<p class="text-muted"><?php echo Text::sprintf('COM_JOOMCOOKIES_SCAN_LIMITS', (int) $page['limits']['max_pages'], (int) $page['limits']['max_depth'], (int) $page['limits']['max_seconds']); ?></p>
	<input type="hidden" name="task" value="scan.start">
	<input type="hidden" name="scan_id" value="<?php echo (int) ($page['current']['id'] ?? 0); ?>">
	<div class="d-flex flex-wrap gap-2 mb-3">
		<button class="btn btn-primary" type="submit" id="jck-scan-start"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_START'); ?></button>
		<button class="btn btn-secondary" type="button" id="jck-scan-pause"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_PAUSE'); ?></button>
		<button class="btn btn-secondary" type="button" id="jck-scan-resume"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_RESUME'); ?></button>
		<button class="btn btn-danger" type="button" id="jck-scan-cancel"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_CANCEL'); ?></button>
	</div>
	<?php echo $this->token; ?>
</form>
<div class="mb-4">
	<div class="d-flex justify-content-between"><span><?php echo Text::_('COM_JOOMCOOKIES_SCAN_STATUS'); ?>: <strong id="jck-scan-status"><?php echo $this->escape((string) ($progress['status'] ?? ($page['current']['status'] ?? '-'))); ?></strong></span><span id="jck-scan-percent"><?php echo (int) ($progress['percent_final'] ?? $progress['percent'] ?? 0); ?>%</span></div>
	<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) ($progress['percent'] ?? 0); ?>">
		<div class="progress-bar" id="jck-scan-progress" style="width: <?php echo (int) ($progress['percent'] ?? 0); ?>%"></div>
	</div>
	<div class="row g-2 mt-2 small">
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_DISCOVERED'); ?>: <span id="jck-scan-discovered"><?php echo (int) ($progress['discovered'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_PAGES'); ?>: <span id="jck-scan-pages"><?php echo (int) ($progress['pages'] ?? $stats['pages'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_PENDING'); ?>: <span id="jck-scan-pending"><?php echo (int) ($progress['pending'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_ERRORS'); ?>: <span id="jck-scan-errors"><?php echo (int) ($progress['errors'] ?? 0); ?></span></div>
		<div class="col-md-2"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_ELAPSED'); ?>: <span id="jck-scan-elapsed"><?php echo (int) ($progress['elapsed'] ?? 0); ?>s</span></div>
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
		<div class="col-md-4">
			<label class="form-label" for="scan_max_seconds"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_SECONDS'); ?></label>
			<input class="form-control" id="scan_max_seconds" type="number" name="jform[scan_max_seconds]" value="<?php echo (int) $page['limits']['max_seconds']; ?>" min="3" max="25">
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
				<td><?php echo ((int) $row['observed'] === 1) ? Text::_('COM_JOOMCOOKIES_OBSERVED') : Text::_('COM_JOOMCOOKIES_REFERENCE'); ?></td>
				<td><?php echo $this->escape((string) $row['category_suggested']); ?></td>
				<td><?php echo $this->escape((string) ($row['page_url'] ?? '')); ?><?php echo !empty($row['page_count']) && (int) $row['page_count'] > 1 ? ' (+' . ((int) $row['page_count'] - 1) . ')' : ''; ?></td>
				<td><?php echo (int) ($row['occurrences'] ?? 1); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php
$wa = $this->getDocument()->getWebAssetManager();
$wa->registerAndUseScript('com_joomcookies.scan', 'com_joomcookies/js/scan.js', [], ['defer' => true]);
