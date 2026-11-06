<?php

namespace JoomCookies\Component\Joomcookies\Administrator\View\Service;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $item = [];

    public function display($tpl = null): void
    {
        $this->item = $this->getModel()->getItem();
        $this->prepare('COM_JOOMCOOKIES_NAV_SERVICES');
        parent::display($tpl);
    }
}
