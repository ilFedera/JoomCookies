<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

include dirname(__DIR__) . '/_nav.php';
?>
<p class="text-muted"><?php echo Text::_('COM_JOOMCOOKIES_COOKIES_INTRO'); ?></p>
<form action="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="mb-3">
	<input type="hidden" name="task" value="scan.cookiesCsv"><button class="btn btn-outline-secondary btn-sm" type="submit">Export CSV</button><?php echo $this->token; ?>
</form>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_NAME'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_DOMAIN'); ?></th>
			<th>Path</th><th><?php echo Text::_('COM_JOOMCOOKIES_COL_SOURCE'); ?></th><th>Scenario</th><th>Party</th><th>Duration</th><th>Status</th><th>Last observed</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($this->items as $row) : ?>
			<tr>
				<td><?php echo $this->escape((string) $row['name']); ?></td>
				<td><?php echo $this->escape((string) $row['domain']); ?></td>
				<td><?php echo $this->escape((string) $row['path']); ?></td><td><?php echo $this->escape((string) $row['source']); ?></td><td><?php echo $this->escape((string) $row['scenario']); ?></td><td><?php echo (int) $row['first_party'] ? 'First party' : 'Third party'; ?></td><td><?php echo $this->escape((string) $row['duration']); ?></td><td><?php echo $this->escape((string) $row['status']); ?></td><td><?php echo $this->escape((string) $row['last_observed_at']); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
