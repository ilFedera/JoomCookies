<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$item = $this->item;
include dirname(__DIR__) . '/_nav.php';
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
	<input type="hidden" name="jform[id]" value="<?php echo (int) ($item['id'] ?? 0); ?>">
	<input type="hidden" name="task" value="service.save">
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_SERVICE_ALIAS'); ?></label>
		<input class="form-control" name="jform[alias]" value="<?php echo $this->escape((string) ($item['alias'] ?? '')); ?>">
	</div>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_COL_NAME'); ?></label>
		<input class="form-control" name="jform[name]" value="<?php echo $this->escape((string) ($item['name'] ?? '')); ?>">
	</div>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_SERVICE_PROVIDER'); ?></label>
		<input class="form-control" name="jform[provider]" value="<?php echo $this->escape((string) ($item['provider'] ?? '')); ?>">
	</div>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_COL_CATEGORY'); ?></label>
		<select class="form-select" name="jform[category]">
			<?php foreach (['statistics', 'marketing', 'embeds', 'unclassified'] as $cat) : ?>
				<option value="<?php echo $cat; ?>"<?php echo (($item['category'] ?? '') === $cat) ? ' selected' : ''; ?>><?php echo $cat; ?></option>
			<?php endforeach; ?>
		</select>
		<?php if (!empty($item['category_suggested'])) : ?>
			<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_COL_SUGGESTED'); ?>: <?php echo $this->escape((string) $item['category_suggested']); ?></div>
		<?php endif; ?>
	</div>
	<?php if (!empty($item['id'])) : ?>
		<p class="text-muted">
			<?php echo Text::_('COM_JOOMCOOKIES_COL_STATUS'); ?>: <?php echo $this->escape((string) ($item['classification_status'] ?? '')); ?>
			— <?php echo Text::_('COM_JOOMCOOKIES_COL_SOURCE'); ?>: <?php echo $this->escape((string) ($item['source'] ?? '')); ?>
			— <?php echo Text::_('COM_JOOMCOOKIES_COL_EVIDENCE'); ?>: <?php echo $this->escape((string) ($item['last_evidence'] ?? '')); ?>
			— <?php echo Text::_('COM_JOOMCOOKIES_COL_PAGES'); ?>: <?php echo (int) ($item['page_count'] ?? 0); ?>
		</p>
	<?php endif; ?>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_SERVICE_MATCH_TYPE'); ?></label>
		<select class="form-select" name="jform[match_type]">
			<?php foreach (['domain', 'url_contains', 'script_id', 'content_contains'] as $type) : ?>
				<option value="<?php echo $type; ?>"<?php echo (($item['match_type'] ?? '') === $type) ? ' selected' : ''; ?>><?php echo $type; ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_SERVICE_MATCH_VALUE'); ?></label>
		<input class="form-control" name="jform[match_value]" value="<?php echo $this->escape((string) ($item['match_value'] ?? '')); ?>">
	</div>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_SERVICE_COOKIES'); ?></label>
		<input class="form-control" name="jform[cookies]" value="<?php echo $this->escape((string) ($item['cookies'] ?? '')); ?>">
	</div>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_SERVICE_NOTES'); ?></label>
		<textarea class="form-control" name="jform[notes]"><?php echo $this->escape((string) ($item['notes'] ?? '')); ?></textarea>
	</div>
	<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php echo $this->token; ?>
</form>
