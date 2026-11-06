<?php

namespace JoomCookies\Component\Joomcookies\Administrator\View\Cookies;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $items = [];

    public function display($tpl = null): void
    {
        $this->items = $this->getModel()->getItems();
        $this->prepare('COM_JOOMCOOKIES_NAV_COOKIES');
        parent::display($tpl);
    }
}
