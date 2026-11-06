<?php

namespace JoomCookies\Component\Joomcookies\Administrator\View\Texts;

\defined('_JEXEC') or die;

use JoomCookies\Component\Joomcookies\Administrator\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $settings = [];

    public function display($tpl = null): void
    {
        $this->settings = $this->getModel()->getSettings();
        $this->prepare('COM_JOOMCOOKIES_NAV_TEXTS');
        parent::display($tpl);
    }
}
