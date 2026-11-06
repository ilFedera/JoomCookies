<?php

namespace JoomCookies\Component\Joomcookies\Administrator\View\Categories;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $items = [];

    public function display($tpl = null): void
    {
        $this->items = $this->getModel()->getItems();
        $this->prepare('COM_JOOMCOOKIES_NAV_CATEGORIES');
        parent::display($tpl);
    }
}
