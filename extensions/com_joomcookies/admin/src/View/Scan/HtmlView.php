<?php

namespace JoomCookies\Component\Joomcookies\Administrator\View\Scan;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $page = [];

    public function display($tpl = null): void
    {
        $this->page = $this->getModel()->getScanPage();
        $this->prepare('COM_JOOMCOOKIES_NAV_SCAN');
        parent::display($tpl);
    }
}
