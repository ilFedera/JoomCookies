<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$current = $this->getName();
?>
<nav class="mb-3">
	<ul class="nav nav-pills flex-wrap">
		<?php foreach ($this->nav as $item) : ?>
			<li class="nav-item">
				<a class="nav-link<?php echo $current === $item['view'] ? ' active' : ''; ?>"
					href="<?php echo Route::_('index.php?option=com_joomcookies&view=' . $item['view']); ?>">
					<?php echo Text::_($item['label']); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
