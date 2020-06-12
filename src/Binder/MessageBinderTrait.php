<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Schemas\Contexts\AppV1;
use Gdbots\Schemas\Contexts\CloudV1;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

trait MessageBinderTrait
{
    private ContainerInterface $container;
    private ?RequestStack $requestStack = null;

    protected function restrictBindFromInput(PbjxEvent $pbjxEvent, Message $message, array $fields, array $input): void
    {
        foreach ($fields as $field) {
            if (!$message->has($field)) {
                // this means whatever was in the input never made it to the message.
                continue;
            }

            if (!isset($input[$field])) {
                // the field in question doesn't exist in the input used to populate the message.
                // so whatever the value is was either a default or set by another process.
                continue;
            }

            // the input was used to populate the field on the message but they weren't allowed
            // to provide that field, only the server can set it.
            $message->clear($field);
        }
    }

    protected function bindApp(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has(CommandV1Mixin::CTX_APP_FIELD) || !$this->container->hasParameter('app_vendor')) {
            return;
        }

        static $app = null;
        if (null === $app) {
            $name = $this->container->getParameter('app_name');
            if ($request->attributes->getBoolean('pbjx_console')) {
                $name .= '-php.console';
            }

            $app = AppV1::create()
                ->set(AppV1::VENDOR_FIELD, $this->container->getParameter('app_vendor'))
                ->set(AppV1::NAME_FIELD, $name)
                ->set(AppV1::VERSION_FIELD, $this->container->getParameter('app_version') ?: null)
                ->set(AppV1::BUILD_FIELD, $this->container->getParameter('app_build') ?: null);
        }

        $message->set(CommandV1Mixin::CTX_APP_FIELD, $app);
    }

    protected function bindCloud(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has(CommandV1Mixin::CTX_CLOUD_FIELD)
            || !$this->container->hasParameter('cloud_provider')
        ) {
            return;
        }

        static $cloud = null;
        if (null === $cloud) {
            $cloud = CloudV1::create()
                ->set(CloudV1::PROVIDER_FIELD, $this->container->getParameter('cloud_provider') ?: null)
                ->set(CloudV1::REGION_FIELD, $this->container->getParameter('cloud_region') ?: null)
                ->set(CloudV1::ZONE_FIELD, $this->container->getParameter('cloud_zone') ?: null)
                ->set(CloudV1::INSTANCE_ID_FIELD, $this->container->getParameter('cloud_instance_id') ?: null)
                ->set(CloudV1::INSTANCE_TYPE_FIELD, $this->container->getParameter('cloud_instance_type') ?: null);
        }

        $message->set(CommandV1Mixin::CTX_CLOUD_FIELD, $cloud);
    }

    protected function bindIp(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has(CommandV1Mixin::CTX_IP_FIELD)
            || $message->has(CommandV1Mixin::CTX_IPV6_FIELD)
        ) {
            return;
        }

        $ip = (string)$request->getClientIp();
        if (empty($ip)) {
            return;
        }

        if (strpos($ip, ':') !== false) {
            $message->set(CommandV1Mixin::CTX_IPV6_FIELD, $ip);
        } else {
            $message->set(CommandV1Mixin::CTX_IP_FIELD, $ip);
        }
    }

    protected function bindUserAgent(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has(CommandV1Mixin::CTX_UA_FIELD)) {
            return;
        }

        $message->set(CommandV1Mixin::CTX_UA_FIELD, $request->headers->get('User-Agent'));
    }

    protected function getRequestStack(): RequestStack
    {
        if (null === $this->requestStack) {
            $this->requestStack = $this->container->get('request_stack');
        }

        return $this->requestStack;
    }

    protected function getCurrentRequest(): Request
    {
        return $this->getRequestStack()->getCurrentRequest() ?: new Request();
    }
}
