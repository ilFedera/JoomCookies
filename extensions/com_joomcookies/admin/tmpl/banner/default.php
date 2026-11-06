<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$s = $this->settings;
include dirname(__DIR__) . '/_nav.php';
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
	<input type="hidden" name="task" value="config.save">
	<input type="hidden" name="return_view" value="banner">
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_POSITION'); ?></label>
		<select class="form-select" name="jform[position]">
			<?php foreach (['bottom', 'top', 'box-left', 'box-right', 'modal'] as $pos) : ?>
				<option value="<?php echo $pos; ?>"<?php echo (($s['position'] ?? 'bottom') === $pos) ? ' selected' : ''; ?>><?php echo $pos; ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php foreach (['color_background' => '#111827', 'color_text' => '#F9FAFB', 'color_accent' => '#2563EB', 'color_button_text' => '#FFFFFF', 'color_muted' => '#D1D5DB'] as $key => $default) : ?>
		<div class="mb-3">
			<label class="form-label"><?php echo $this->escape($key); ?></label>
			<input class="form-control" type="color" name="jform[<?php echo $key; ?>]" value="<?php echo $this->escape((string) ($s[$key] ?? $default)); ?>">
		</div>
	<?php endforeach; ?>
	<div class="mb-3">
		<label class="form-label"><?php echo Text::_('COM_JOOMCOOKIES_REOPEN'); ?></label>
		<select class="form-select" name="jform[show_reopen_button]">
			<option value="1"<?php echo (int) ($s['show_reopen_button'] ?? 1) === 1 ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
			<option value="0"<?php echo (int) ($s['show_reopen_button'] ?? 1) === 0 ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
		</select>
	</div>
	<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php echo $this->token; ?>
</form>
