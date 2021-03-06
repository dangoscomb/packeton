<?php

declare(strict_types=1);

namespace Packagist\WebBundle\Webhook;

use Packagist\WebBundle\Entity\Webhook;
use Packagist\WebBundle\Webhook\Twig\ContextAwareInterface;
use Packagist\WebBundle\Webhook\Twig\PayloadRenderer;
use Packagist\WebBundle\Webhook\Twig\PlaceholderContext;
use Packagist\WebBundle\Webhook\Twig\PlaceholderExtension;
use Packagist\WebBundle\Webhook\Twig\WebhookContext;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class RequestResolver implements ContextAwareInterface, LoggerAwareInterface
{
    private $logger;
    private $renderer;

    /**
     * @param PayloadRenderer $renderer
     */
    public function __construct(PayloadRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * @param Webhook $webhook
     * @param array $context
     *
     * @return HookRequest[]
     */
    public function resolveHook(Webhook $webhook, array $context = [])
    {
        return iterator_to_array($this->doResolveHook($webhook, $context));
    }

    /**
     * @param Webhook $webhook
     * @param array $context
     * @return \Generator|void
     */
    private function doResolveHook(Webhook $webhook, array $context = [])
    {
        $separator = '-------------' . sha1(random_bytes(10)) . '---------------';
        $context[PlaceholderExtension::VARIABLE_NAME] = $placeholder = new PlaceholderContext();

        if (null !== $this->logger) {
            $this->renderer->setLogger($this->logger);
        }

        if ($payload = $webhook->getPayload()) {
            $payload = (string) $this->renderer->createTemplate($payload)->render($context);
            $content = $webhook->getUrl() . $separator . trim($payload);
        } else {
            $content = $webhook->getUrl() . $separator;
        }

        foreach ($placeholder->walkContent($content) as $content) {
            list($url, $content) = explode($separator, $content);
            yield new HookRequest($url, $webhook->getMethod(), $webhook->getOptions() ?: [], $content ? trim($content) : null);
        }
    }

    /**
     * @inheritDoc
     */
    public function setContext(WebhookContext $context = null): void
    {
        $this->renderer->setContext($context);
    }

    /**
     * @inheritDoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
