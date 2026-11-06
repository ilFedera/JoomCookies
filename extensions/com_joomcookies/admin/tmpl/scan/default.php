<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$page = $this->page;
include dirname(__DIR__) . '/_nav.php';
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="mb-4">
	<div class="mb-3">
		<label class="form-label" for="start_url"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_START_URL'); ?></label>
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
		<div class="col-md-4"><input class="form-control" type="number" name="jform[scan_max_pages]" value="<?php echo (int) $page['limits']['max_pages']; ?>" min="1" max="50"></div>
		<div class="col-md-4"><input class="form-control" type="number" name="jform[scan_max_depth]" value="<?php echo (int) $page['limits']['max_depth']; ?>" min="0" max="5"></div>
		<div class="col-md-4"><input class="form-control" type="number" name="jform[scan_max_seconds]" value="<?php echo (int) $page['limits']['max_seconds']; ?>" min="5" max="90"></div>
	</div>
	<button class="btn btn-secondary mt-2" type="submit"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_SAVE_LIMITS'); ?></button>
	<?php echo $this->token; ?>
</form>
<div class="alert alert-warning">
	<?php echo $this->escape((string) $page['browser']); ?>
	<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="d-inline">
		<input type="hidden" name="task" value="scan.browserUnavailable">
		<button class="btn btn-sm btn-outline-secondary" type="submit"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_BROWSER'); ?></button>
		<?php echo $this->token; ?>
	</form>
</div>
<?php if (!empty($page['diff'])) : ?>
	<div class="alert alert-info">
		<?php echo Text::sprintf('COM_JOOMCOOKIES_SCAN_DIFF', \count($page['diff']['added']), \count($page['diff']['removed']), (int) $page['diff']['unchanged']); ?>
	</div>
<?php endif; ?>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_TYPE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_NAME'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_DOMAIN'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_EVIDENCE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_CATEGORY'); ?></th>
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
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
