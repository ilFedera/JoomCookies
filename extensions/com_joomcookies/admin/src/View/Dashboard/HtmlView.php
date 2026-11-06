<?php

namespace JoomCookies\Component\Joomcookies\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $stateData = [];

    public function display($tpl = null): void
    {
        $this->stateData = $this->getModel()->getStateData();
        $this->prepare('COM_JOOMCOOKIES_NAV_DASHBOARD');
        parent::display($tpl);
    }
}
