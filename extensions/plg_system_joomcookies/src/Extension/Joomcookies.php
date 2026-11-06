<?php

/**
 * @package     JoomCookies
 * @subpackage  plg_system_joomcookies
 */

namespace JoomCookies\Plugin\System\Joomcookies\Extension;

\defined('_JEXEC') or die;

use JoomCookies\Plugin\System\Joomcookies\Service\BannerRenderer;
use JoomCookies\Plugin\System\Joomcookies\Service\ConfigBuilder;
use JoomCookies\Plugin\System\Joomcookies\Service\ConsentState;
use JoomCookies\Plugin\System\Joomcookies\Service\HtmlGuard;
use JoomCookies\Plugin\System\Joomcookies\Service\ServiceRegistry;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\EventInterface;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;

/**
 * System plugin that stores consent locally and blocks managed non-essential
 * scripts/iframes before they execute.
 */
final class Joomcookies extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $runtimeConfig = null;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare'    => 'onContentPrepare',
            'onBeforeCompileHead' => ['onBeforeCompileHead', Priority::LOW],
            'onAfterRender'       => ['onAfterRender', Priority::MIN],
        ];
    }

    public function onContentPrepare(EventInterface $event): void
    {
        if (!$this->isActiveSite()) {
            return;
        }

        $item = $this->eventItem($event);

        if (!\is_object($item) || empty($item->text) || !\is_string($item->text)) {
            return;
        }

        try {
            $item->text = $this->guard()->replaceShortcodes($item->text);
            $item->text = $this->guard()->replacePreferenceShortcodes($item->text);
        } catch (\Throwable) {
            // Content must never break the page.
        }
    }

    public function onBeforeCompileHead(EventInterface $event): void
    {
        if (!$this->isActiveSite()) {
            return;
        }

        $app = $this->getApplication();
        $doc = $app->getDocument();

        if (!$doc instanceof HtmlDocument) {
            return;
        }

        try {
            $wa = $doc->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('plg_system_joomcookies');
            $wa->useStyle('plg_system_joomcookies.core');
            $wa->useScript('plg_system_joomcookies.core');
            $doc->addStyleDeclaration(BannerRenderer::cssVariables($this->config()));
        } catch (\Throwable) {
            return;
        }
    }

    public function onAfterRender(EventInterface $event): void
    {
        if (!$this->isActiveSite()) {
            return;
        }

        $app = $this->getApplication();

        try {
            $body = $app->getBody();

            if ($body === '' || !str_contains($body, '<')) {
                return;
            }

            $config = $this->config();
            $guard  = $this->guard();
            $body   = $guard->process($body);
            $markup = BannerRenderer::markup($config);
            $json   = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

            if ($json === false) {
                return;
            }

            $configTag = '<script type="application/json" id="joomcookies-config">' . $json . '</script>';

            if (!str_contains($body, 'id="joomcookies-config"')) {
                $body = self::injectBeforeBody($body, $configTag);
            }

            if (!str_contains($body, 'data-jck-root="1"')) {
                $body = self::injectBeforeBody($body, $markup);
            }

            if (!empty($config['ui']['diagnostic'])) {
                $summary = htmlspecialchars(json_encode($guard->blocked(), JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
                $body    = self::injectBeforeBody($body, '<!-- joomcookies-blocked ' . $summary . ' -->');
            }

            $app->setBody($body);
        } catch (\Throwable) {
            // Fail open for site availability; blocking is skipped rather than taking the site down.
        }
    }

    private function isActiveSite(): bool
    {
        $app = $this->getApplication();

        if (!$app->isClient('site') || !$app->getDocument() instanceof HtmlDocument) {
            return false;
        }

        return !empty($this->config()['enabled']);
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        if ($this->runtimeConfig !== null) {
            return $this->runtimeConfig;
        }

        $source = (string) $this->params->get('config_source', 'auto');
        $path   = Uri::root(true) ?: '/';
        $bridge = ($source === 'plugin') ? null : ComponentConfigBridge::load();

        if ($source === 'component' && $bridge === null) {
            $this->runtimeConfig = ConfigBuilder::fromParams($this->params, $path);
        } elseif ($bridge !== null && $source !== 'plugin') {
            $this->runtimeConfig = ConfigBuilder::fromArray($bridge['settings'], $path, $bridge['services']);
        } else {
            $this->runtimeConfig = ConfigBuilder::fromParams($this->params, $path);
        }

        return $this->runtimeConfig;
    }

    private function extraServices(): array
    {
        $source = (string) $this->params->get('config_source', 'auto');
        $bridge = ($source === 'plugin') ? null : ComponentConfigBridge::load();

        return $bridge['services'] ?? [];
    }

    private function guard(): HtmlGuard
    {
        $config   = $this->config();
        $registry = new ServiceRegistry(array_merge(
            ConfigBuilder::services($this->params->get('services', [])),
            $this->extraServices()
        ));

        return new HtmlGuard($registry, ConfigBuilder::guardOptions($config), $this->currentConsent());
    }

    /**
     * @return array<string, bool>
     */
    private function currentConsent(): array
    {
        $config = $this->config();
        $name   = (string) ($config['cookie']['name'] ?? 'joomcookies_consent');
        $cookie = $this->getApplication()->getInput()->cookie->get($name, '', 'string');
        $state  = ConsentState::decode((string) $cookie);

        if ($state === null || !ConsentState::matchesVersion($state, (string) $config['version'])) {
            return ConsentState::deniedOptional();
        }

        return $state['c'];
    }

    private function eventItem(EventInterface $event): mixed
    {
        if (method_exists($event, 'getItem')) {
            return $event->getItem();
        }

        $args = $event->getArguments();

        return $args['subject'] ?? $args['item'] ?? $args[0] ?? null;
    }

    private static function injectBeforeBody(string $html, string $snippet): string
    {
        $pos = strripos($html, '</body>');

        if ($pos === false) {
            return $html . $snippet;
        }

        return substr($html, 0, $pos) . $snippet . substr($html, $pos);
    }
}
