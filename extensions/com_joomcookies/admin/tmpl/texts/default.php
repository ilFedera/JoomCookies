<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$s = $this->settings;
include dirname(__DIR__) . '/_nav.php';
$fields = [
	'text_title', 'text_message', 'text_accept', 'text_reject', 'text_customize', 'text_save',
	'text_close', 'text_reopen', 'text_prefs_title', 'text_cookie_link', 'text_privacy_link',
	'cookie_policy_url', 'privacy_policy_url',
];
?>
<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
	<input type="hidden" name="task" value="config.save">
	<input type="hidden" name="return_view" value="texts">
	<?php foreach ($fields as $key) : ?>
		<div class="mb-3">
			<label class="form-label"><?php echo $this->escape($key); ?></label>
			<?php if ($key === 'text_message') : ?>
				<textarea class="form-control" name="jform[<?php echo $key; ?>]"><?php echo $this->escape((string) ($s[$key] ?? '')); ?></textarea>
			<?php else : ?>
				<input class="form-control" name="jform[<?php echo $key; ?>]" value="<?php echo $this->escape((string) ($s[$key] ?? '')); ?>">
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
	<button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php echo $this->token; ?>
</form>
