<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$data = $this->stateData;
include dirname(__DIR__) . '/_nav.php';
?>
<div class="row g-3">
	<div class="col-md-6">
		<div class="card">
			<div class="card-body">
				<h2 class="h5"><?php echo Text::_('COM_JOOMCOOKIES_STATUS'); ?></h2>
				<p><?php echo Text::_('COM_JOOMCOOKIES_PLUGIN'); ?>:
					<strong><?php echo !empty($data['plugin_enabled']) ? Text::_('JENABLED') : Text::_('JDISABLED'); ?></strong></p>
				<p><?php echo Text::_('COM_JOOMCOOKIES_BANNER'); ?>:
					<strong><?php echo !empty($data['enabled']) ? Text::_('JENABLED') : Text::_('JDISABLED'); ?></strong></p>
				<p><?php echo Text::_('COM_JOOMCOOKIES_CONSENT_VERSION'); ?>:
					<code><?php echo $this->escape((string) $data['consent_version']); ?></code></p>
				<form action="<?php echo Route::_('index.php?option=com_joomcookies'); ?>" method="post">
					<input type="hidden" name="task" value="config.save">
					<input type="hidden" name="return_view" value="dashboard">
					<div class="mb-2">
						<label class="form-label" for="jck-consent-version"><?php echo Text::_('COM_JOOMCOOKIES_CONSENT_VERSION'); ?></label>
						<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_CONSENT_VERSION_DESC'); ?></div>
						<input class="form-control" id="jck-consent-version" name="jform[consent_version]" value="<?php echo $this->escape((string) $data['consent_version']); ?>">
					</div>
					<div class="mb-2">
						<label class="form-label" for="jck-enabled"><?php echo Text::_('COM_JOOMCOOKIES_BANNER'); ?></label>
						<div class="form-text"><?php echo Text::_('COM_JOOMCOOKIES_BANNER_DESC'); ?></div>
						<select class="form-select" id="jck-enabled" name="jform[enabled]">
							<option value="1"<?php echo !empty($data['enabled']) ? ' selected' : ''; ?>><?php echo Text::_('JENABLED'); ?></option>
							<option value="0"<?php echo empty($data['enabled']) ? ' selected' : ''; ?>><?php echo Text::_('JDISABLED'); ?></option>
						</select>
					</div>
					<button class="btn btn-sm btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
					<?php echo $this->token; ?>
				</form>
			</div>
		</div>
	</div>
	<div class="col-md-6">
		<div class="card">
			<div class="card-body">
				<h2 class="h5"><?php echo Text::_('COM_JOOMCOOKIES_NAV_SCAN'); ?></h2>
				<?php if (!empty($data['last_scan'])) : ?>
					<p><?php echo $this->escape((string) $data['last_scan']['status']); ?>
						— <?php echo $this->escape((string) $data['last_scan']['started_at']); ?></p>
				<?php else : ?>
					<p><?php echo Text::_('COM_JOOMCOOKIES_SCAN_NONE'); ?></p>
				<?php endif; ?>
				<p><a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_joomcookies&view=scan'); ?>"><?php echo Text::_('COM_JOOMCOOKIES_NAV_SCAN'); ?></a></p>
			</div>
		</div>
	</div>
</div>
