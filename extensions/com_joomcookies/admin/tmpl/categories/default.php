<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

include dirname(__DIR__) . '/_nav.php';
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
	<input type="hidden" name="task" value="category.save">
	<?php foreach ($this->items as $row) : ?>
		<div class="card mb-3">
			<div class="card-body">
				<h2 class="h5"><?php echo $this->escape((string) $row['alias']); ?>
					<?php if ((int) $row['required']) : ?>
						<span class="badge bg-secondary"><?php echo Text::_('COM_JOOMCOOKIES_REQUIRED'); ?></span>
					<?php endif; ?>
				</h2>
				<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_CAT_TITLE'); ?></label>
				<input class="form-control mb-2" name="jform[<?php echo (int) $row['id']; ?>][title]" value="<?php echo $this->escape((string) $row['title']); ?>">
				<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_CAT_DESCRIPTION'); ?></label>
				<textarea class="form-control" name="jform[<?php echo (int) $row['id']; ?>][description]"><?php echo $this->escape((string) $row['description']); ?></textarea>
			</div>
		</div>
	<?php endforeach; ?>
	<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php echo $this->token; ?>
</form>
