<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$s = $this->settings;
include dirname(__DIR__) . '/_nav.php';
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
	<input type="hidden" name="task" value="config.save">
	<input type="hidden" name="return_view" value="youtube">
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_YOUTUBE_NOCOOKIE'); ?></label>
		<select class="form-select" name="jform[youtube_nocookie]">
			<option value="1"<?php echo (int) ($s['youtube_nocookie'] ?? 1) === 1 ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
			<option value="0"<?php echo (int) ($s['youtube_nocookie'] ?? 1) === 0 ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
		</select>
	</div>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_YOUTUBE_THUMBNAILS'); ?></label>
		<select class="form-select" name="jform[youtube_thumbnails]">
			<option value="local"<?php echo (($s['youtube_thumbnails'] ?? 'local') === 'local') ? ' selected' : ''; ?>>local</option>
			<option value="youtube"<?php echo (($s['youtube_thumbnails'] ?? '') === 'youtube') ? ' selected' : ''; ?>>youtube</option>
		</select>
	</div>
	<?php foreach (['text_youtube_blocked', 'text_youtube_load', 'text_youtube_help', 'text_embed_blocked', 'text_embed_load'] as $key) : ?>
		<div class="mb-3">
			<label class="form-label"><?php echo $this->escape($key); ?></label>
			<input class="form-control" name="jform[<?php echo $key; ?>]" value="<?php echo $this->escape((string) ($s[$key] ?? '')); ?>">
		</div>
	<?php endforeach; ?>
	<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php echo $this->token; ?>
</form>
