<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$s = $this->settings;
include dirname(__DIR__) . '/_nav.php';
$fields = [
	'text_title'         => ['label' => 'COM_JOOMCOOKIES_TEXT_TITLE', 'type' => 'text'],
	'text_message'       => ['label' => 'COM_JOOMCOOKIES_TEXT_MESSAGE', 'type' => 'textarea'],
	'text_accept'        => ['label' => 'COM_JOOMCOOKIES_TEXT_ACCEPT', 'type' => 'text'],
	'text_reject'        => ['label' => 'COM_JOOMCOOKIES_TEXT_REJECT', 'type' => 'text'],
	'text_customize'     => ['label' => 'COM_JOOMCOOKIES_TEXT_CUSTOMIZE', 'type' => 'text'],
	'text_save'          => ['label' => 'COM_JOOMCOOKIES_TEXT_SAVE', 'type' => 'text'],
	'text_close'         => ['label' => 'COM_JOOMCOOKIES_TEXT_CLOSE', 'type' => 'text'],
	'text_reopen'        => ['label' => 'COM_JOOMCOOKIES_TEXT_REOPEN', 'type' => 'text'],
	'text_prefs_title'   => ['label' => 'COM_JOOMCOOKIES_TEXT_PREFS_TITLE', 'type' => 'text'],
	'text_prefs_desc'    => ['label' => 'COM_JOOMCOOKIES_TEXT_PREFS_DESC', 'type' => 'textarea'],
	'text_cookie_link'   => ['label' => 'COM_JOOMCOOKIES_TEXT_COOKIE_LINK', 'type' => 'text'],
	'text_privacy_link'  => ['label' => 'COM_JOOMCOOKIES_TEXT_PRIVACY_LINK', 'type' => 'text'],
	'cookie_policy_url'  => ['label' => 'COM_JOOMCOOKIES_COOKIE_POLICY_URL', 'type' => 'text'],
	'privacy_policy_url' => ['label' => 'COM_JOOMCOOKIES_PRIVACY_POLICY_URL', 'type' => 'text'],
];
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
	<input type="hidden" name="task" value="config.save">
	<input type="hidden" name="return_view" value="texts">
	<?php foreach ($fields as $key => $meta) : ?>
		<div class="mb-3">
			<label class="form-label" for="jck-<?php echo $key; ?>"><?php echo Text::_($meta['label']); ?></label>
			<?php if ($meta['type'] === 'textarea') : ?>
				<textarea class="form-control" id="jck-<?php echo $key; ?>" name="jform[<?php echo $key; ?>]" rows="4"><?php echo $this->escape((string) ($s[$key] ?? '')); ?></textarea>
			<?php else : ?>
				<input class="form-control" id="jck-<?php echo $key; ?>" name="jform[<?php echo $key; ?>]" value="<?php echo $this->escape((string) ($s[$key] ?? '')); ?>">
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
	<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php echo $this->token; ?>
</form>
