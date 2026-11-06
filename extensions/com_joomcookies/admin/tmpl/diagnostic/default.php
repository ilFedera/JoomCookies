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
<h2 class="h5"><?php echo Text::_('COM_JOOMCOOKIES_DIAG_RULES'); ?></h2>
<p class="text-muted"><?php echo Text::_('COM_JOOMCOOKIES_DIAG_RULES_INTRO'); ?></p>
<table class="table mb-4">
	<thead>
		<tr>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_NAME'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_CATEGORY'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_SOURCE'); ?></th>
			<th><?php echo Text::_('JENABLED'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_COVERAGE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_VERIFIED'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_PROBLEM'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach (($report['rules'] ?? []) as $rule) : ?>
			<tr>
				<td><?php echo $this->escape((string) $rule['service']); ?><div class="small text-muted"><?php echo $this->escape((string) $rule['alias']); ?></div></td>
				<td><?php echo $this->escape((string) $rule['category']); ?></td>
				<td><?php echo $this->escape((string) $rule['origin']); ?></td>
				<td><?php echo !empty($rule['enabled']) ? Text::_('JYES') : Text::_('JNO'); ?></td>
				<td><?php echo $this->escape((string) $rule['coverage']); ?></td>
				<td><?php echo $rule['verified'] !== '' ? $this->escape((string) $rule['verified']) : Text::_('COM_JOOMCOOKIES_NOT_VERIFIED'); ?></td>
				<td><?php echo $this->escape((string) $rule['problem']); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
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
