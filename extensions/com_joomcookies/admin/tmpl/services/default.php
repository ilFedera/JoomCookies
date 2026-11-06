<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

include dirname(__DIR__) . '/_nav.php';
?>
<p>
	<a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_joomcookies&view=service&layout=edit'); ?>">
		<?php echo Text::_('COM_JOOMCOOKIES_SERVICE_NEW'); ?>
	</a>
</p>
<p class="text-muted"><?php echo Text::_('COM_JOOMCOOKIES_SERVICES_INTRO'); ?></p>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_NAME'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_SOURCE'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_CATEGORY'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_APPROVED'); ?></th>
			<th><?php echo Text::_('COM_JOOMCOOKIES_COL_BLOCK'); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($this->items as $row) : ?>
			<tr>
				<td>
					<a href="<?php echo Route::_('index.php?option=com_joomcookies&view=service&layout=edit&id=' . (int) $row['id']); ?>">
						<?php echo $this->escape((string) $row['name']); ?>
					</a>
					<div class="small text-muted"><?php echo $this->escape((string) $row['match_value']); ?></div>
				</td>
				<td><?php echo $this->escape((string) $row['source']); ?></td>
				<td><?php echo $this->escape((string) $row['category']); ?></td>
				<td><?php echo (int) $row['approved'] ? Text::_('JYES') : Text::_('JNO'); ?></td>
				<td><?php echo (int) $row['block_enabled'] ? Text::_('JYES') : Text::_('JNO'); ?></td>
				<td>
					<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="d-inline">
						<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
						<?php echo $this->token; ?>
						<?php if (!(int) $row['approved']) : ?>
							<button class="btn btn-sm btn-success" type="submit" name="task" value="service.approve"><?php echo Text::_('COM_JOOMCOOKIES_APPROVE'); ?></button>
						<?php elseif (!(int) $row['block_enabled']) : ?>
							<button class="btn btn-sm btn-warning" type="submit" name="task" value="service.enableBlock"><?php echo Text::_('COM_JOOMCOOKIES_ENABLE_BLOCK'); ?></button>
						<?php else : ?>
							<button class="btn btn-sm btn-secondary" type="submit" name="task" value="service.disableBlock"><?php echo Text::_('COM_JOOMCOOKIES_DISABLE_BLOCK'); ?></button>
						<?php endif; ?>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
