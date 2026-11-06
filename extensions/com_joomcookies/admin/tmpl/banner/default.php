<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$s = $this->settings;
include dirname(__DIR__) . '/_nav.php';
$positions = [
	'bottom'    => 'COM_JOOMCOOKIES_POSITION_BOTTOM',
	'top'       => 'COM_JOOMCOOKIES_POSITION_TOP',
	'box-left'  => 'COM_JOOMCOOKIES_POSITION_BOX_LEFT',
	'box-right' => 'COM_JOOMCOOKIES_POSITION_BOX_RIGHT',
	'modal'     => 'COM_JOOMCOOKIES_POSITION_MODAL',
];
$colors = [
	'color_background'  => ['#111827', 'COM_JOOMCOOKIES_COLOR_BACKGROUND'],
	'color_text'        => ['#F9FAFB', 'COM_JOOMCOOKIES_COLOR_TEXT'],
	'color_accent'      => ['#2563EB', 'COM_JOOMCOOKIES_COLOR_ACCENT'],
	'color_button_text' => ['#FFFFFF', 'COM_JOOMCOOKIES_COLOR_BUTTON_TEXT'],
	'color_muted'       => ['#D1D5DB', 'COM_JOOMCOOKIES_COLOR_MUTED'],
];
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
	<input type="hidden" name="task" value="config.save">
	<input type="hidden" name="return_view" value="banner">
	<div class="mb-3">
		<label class="form-label" for="jck-position"><?php echo Text::_('COM_JOOMCOOKIES_POSITION'); ?></label>
		<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_POSITION_DESC'); ?></div>
		<select class="form-select" id="jck-position" name="jform[position]">
			<?php foreach ($positions as $pos => $label) : ?>
				<option value="<?php echo $pos; ?>"<?php echo (($s['position'] ?? 'bottom') === $pos) ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php foreach ($colors as $key => $meta) : ?>
		<div class="mb-3">
			<label class="form-label" for="jck-<?php echo $key; ?>"><?php echo Text::_($meta[1]); ?></label>
			<input class="form-control form-control-color" id="jck-<?php echo $key; ?>" type="color" name="jform[<?php echo $key; ?>]" value="<?php echo $this->escape((string) ($s[$key] ?? $meta[0])); ?>">
		</div>
	<?php endforeach; ?>
	<div class="mb-3">
		<label class="form-label" for="jck-reopen"><?php echo Text::_('COM_JOOMCOOKIES_REOPEN'); ?></label>
		<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_REOPEN_DESC'); ?></div>
		<select class="form-select" id="jck-reopen" name="jform[show_reopen_button]">
			<option value="1"<?php echo (int) ($s['show_reopen_button'] ?? 1) === 1 ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
			<option value="0"<?php echo (int) ($s['show_reopen_button'] ?? 1) === 0 ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
		</select>
	</div>
	<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php echo $this->token; ?>
</form>
