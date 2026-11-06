<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\View;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\Helper\AdminUi;
use Joomla\CMS\MVC\View\HtmlView as BaseView;

class BaseHtmlView extends BaseView
{
    protected function prepare(string $titleKey): void
    {
        AdminUi::title($titleKey);
        $this->nav = AdminUi::nav();
        $this->token = AdminUi::token();
    }

    /**
     * @var list<array{view: string, label: string}>
     */
    public array $nav = [];

    public string $token = '';
}
