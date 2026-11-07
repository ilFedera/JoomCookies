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
	<p class="text-muted">Le miniature sono sempre locali: il browser non contatta i domini YouTube/Google prima del consenso.</p>
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
<hr>
<h2 class="h5">Miniatura locale</h2>
<p class="text-muted">Scarica manualmente la miniatura per un ID YouTube valido. La richiesta è eseguita dal server solo una volta; i visitatori ricevono esclusivamente il file locale.</p>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post" class="row g-2">
	<input type="hidden" name="task" value="youtube.fetchThumbnail">
	<div class="col-md-5"><input class="form-control" name="video_id" pattern="[A-Za-z0-9_-]{11}" maxlength="11" required placeholder="ID YouTube di 11 caratteri"></div>
	<div class="col-md-3"><button class="btn btn-secondary" type="submit">Scarica miniatura</button></div>
	<?php echo $this->token; ?>
</form>
