<?php

namespace JoomCookies\Component\Joomcookies\Administrator\View\Diagnostic;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $report = [];

    public function display($tpl = null): void
    {
        $this->report = $this->getModel()->getReport();
        $this->prepare('COM_JOOMCOOKIES_NAV_DIAGNOSTIC');
        parent::display($tpl);
    }
}
