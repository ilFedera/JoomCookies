<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$s = $this->settings;
include dirname(__DIR__) . '/_nav.php';
$texts = [
	'text_youtube_blocked' => 'COM_JOOMCOOKIES_TEXT_YOUTUBE_BLOCKED',
	'text_youtube_help'    => 'COM_JOOMCOOKIES_TEXT_YOUTUBE_HELP',
	'text_youtube_prefs'   => 'COM_JOOMCOOKIES_TEXT_YOUTUBE_PREFS',
	'text_youtube_load'    => 'COM_JOOMCOOKIES_TEXT_YOUTUBE_LOAD',
	'text_embed_blocked'   => 'COM_JOOMCOOKIES_TEXT_EMBED_BLOCKED',
	'text_embed_load'      => 'COM_JOOMCOOKIES_TEXT_EMBED_LOAD',
	'text_embed_help'      => 'COM_JOOMCOOKIES_TEXT_EMBED_HELP',
];
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
	<input type="hidden" name="task" value="config.save">
	<input type="hidden" name="return_view" value="youtube">
	<div class="mb-3">
		<label class="form-label" for="jck-yt-nocookie"><?php echo Text::_('COM_JOOMCOOKIES_YOUTUBE_NOCOOKIE'); ?></label>
		<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_YOUTUBE_NOCOOKIE_DESC'); ?></div>
		<select class="form-select" id="jck-yt-nocookie" name="jform[youtube_nocookie]">
			<option value="1"<?php echo (int) ($s['youtube_nocookie'] ?? 1) === 1 ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
			<option value="0"<?php echo (int) ($s['youtube_nocookie'] ?? 1) === 0 ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
		</select>
	</div>
	<div class="mb-3">
		<label class="form-label" for="jck-yt-thumbs"><?php echo Text::_('COM_JOOMCOOKIES_YOUTUBE_THUMBNAILS'); ?></label>
		<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_YOUTUBE_THUMBNAILS_DESC'); ?></div>
		<select class="form-select" id="jck-yt-thumbs" name="jform[youtube_thumbnails]">
			<option value="local"<?php echo (($s['youtube_thumbnails'] ?? 'local') === 'local') ? ' selected' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_YOUTUBE_THUMBNAILS_LOCAL'); ?></option>
			<option value="youtube"<?php echo (($s['youtube_thumbnails'] ?? '') === 'youtube') ? ' selected' : ''; ?>><?php echo Text::_('COM_JOOMCOOKIES_YOUTUBE_THUMBNAILS_YOUTUBE'); ?></option>
		</select>
	</div>
	<?php foreach ($texts as $key => $label) : ?>
		<div class="mb-3">
			<label class="form-label" for="jck-<?php echo $key; ?>"><?php echo Text::_($label); ?></label>
			<?php if ($key === 'text_youtube_help') : ?>
				<textarea class="form-control" id="jck-<?php echo $key; ?>" name="jform[<?php echo $key; ?>]" rows="3"><?php echo $this->escape((string) ($s[$key] ?? '')); ?></textarea>
			<?php else : ?>
				<input class="form-control" id="jck-<?php echo $key; ?>" name="jform[<?php echo $key; ?>]" value="<?php echo $this->escape((string) ($s[$key] ?? '')); ?>">
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
	<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php echo $this->token; ?>
</form>
