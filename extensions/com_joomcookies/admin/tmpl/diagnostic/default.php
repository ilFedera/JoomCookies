<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$report = $this->report;
include dirname(__DIR__) . '/_nav.php';
?>
<ul class="list-group mb-4">
	<?php foreach ($report['checks'] as $check) : ?>
		<li class="list-group-item d-flex justify-content-between">
			<span><?php echo str_starts_with((string) $check['label'], 'COM_') ? Text::_((string) $check['label']) : $this->escape((string) $check['label']); ?></span>
			<span><?php echo !empty($check['ok']) ? Text::_('JYES') : Text::_('JNO'); ?></span>
		</li>
	<?php endforeach; ?>
</ul>
<p class="text-muted"><?php echo Text::_('COM_JOOMCOOKIES_SCAN_BROWSER_UNAVAILABLE'); ?></p>
<h2 class="h5"><?php echo Text::_('COM_JOOMCOOKIES_LOGS'); ?></h2>
<table class="table">
	<thead>
		<tr><th><?php echo Text::_('JDATE'); ?></th><th><?php echo Text::_('COM_JOOMCOOKIES_COL_EVENT'); ?></th><th><?php echo Text::_('COM_JOOMCOOKIES_COL_MESSAGE'); ?></th></tr>
	</thead>
	<tbody>
		<?php foreach ($report['logs'] as $log) : ?>
			<tr>
				<td><?php echo $this->escape((string) $log['created_at']); ?></td>
				<td><?php echo $this->escape((string) $log['event']); ?></td>
				<td><?php echo $this->escape((string) $log['message']); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
