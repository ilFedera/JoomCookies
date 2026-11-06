<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

include dirname(__DIR__) . '/_nav.php';
?>
<p class="text-muted"><?php echo Text::_('COM_JOOMCOOKIES_COOKIES_INTRO'); ?></p>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_TYPE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_NAME'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_DOMAIN'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_EVIDENCE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_PAGE'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($this->items as $row) : ?>
			<tr>
				<td><?php echo $this->escape((string) $row['type']); ?></td>
				<td><?php echo $this->escape((string) $row['name']); ?></td>
				<td><?php echo $this->escape((string) $row['domain']); ?></td>
				<td><?php
					$kind = (string) ($row['evidence_kind'] ?? '');
					if ($kind === 'observed' || ((int) ($row['observed'] ?? 0) === 1 && ($row['type'] ?? '') === 'cookie_header')) {
						echo Text::_('COM_JOOMCOOKIES_EVIDENCE_SETCOOKIE');
					} elseif ($kind === 'browser') {
						echo Text::_('COM_JOOMCOOKIES_EVIDENCE_BROWSER');
					} else {
						echo Text::_('COM_JOOMCOOKIES_EVIDENCE_REFERENCE');
					}
				?></td>
				<td><?php echo $this->escape((string) $row['page_url']); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
