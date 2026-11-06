<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$page = $this->page;
$stats = $page['stats'] ?? [];
include dirname(__DIR__) . '/_nav.php';
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="mb-4">
	<div class="mb-3">
		<label class="form-label" for="start_url"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_START_URL'); ?></label>
		<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_START_URL_DESC'); ?></div>
		<input class="form-control" type="url" name="start_url" id="start_url" value="<?php echo $this->escape((string) $page['start_url']); ?>">
	</div>
	<p class="text-muted"><?php echo Text::sprintf('COM_JOOMCOOKIES_SCAN_LIMITS', (int) $page['limits']['max_pages'], (int) $page['limits']['max_depth'], (int) $page['limits']['max_seconds']); ?></p>
	<input type="hidden" name="task" value="scan.start">
	<button class="btn btn-primary" type="submit"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_START'); ?></button>
	<?php echo $this->token; ?>
</form>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="mb-4">
	<input type="hidden" name="task" value="config.save">
	<input type="hidden" name="return_view" value="scan">
	<div class="row g-2">
		<div class="col-md-4">
			<label class="form-label" for="scan_max_pages"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_PAGES'); ?></label>
			<input class="form-control" id="scan_max_pages" type="number" name="jform[scan_max_pages]" value="<?php echo (int) $page['limits']['max_pages']; ?>" min="1" max="50">
		</div>
		<div class="col-md-4">
			<label class="form-label" for="scan_max_depth"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_DEPTH'); ?></label>
			<input class="form-control" id="scan_max_depth" type="number" name="jform[scan_max_depth]" value="<?php echo (int) $page['limits']['max_depth']; ?>" min="0" max="5">
		</div>
		<div class="col-md-4">
			<label class="form-label" for="scan_max_seconds"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_MAX_SECONDS'); ?></label>
			<input class="form-control" id="scan_max_seconds" type="number" name="jform[scan_max_seconds]" value="<?php echo (int) $page['limits']['max_seconds']; ?>" min="5" max="90">
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
				<td><?php echo $this->escape((string) $row['page_url']); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
