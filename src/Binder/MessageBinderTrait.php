<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbj\Field;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Schemas\Contexts\AppV1;
use Gdbots\Schemas\Contexts\CloudV1;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

trait MessageBinderTrait
{
    /** @var ContainerInterface */
    protected $container;

    /** @var RequestStack */
    protected $requestStack;

    /**
     * @param PbjxEvent $pbjxEvent
     * @param Message   $message
     * @param Field[]   $fields
     * @param array     $input
     */
    protected function restrictBindFromInput(PbjxEvent $pbjxEvent, Message $message, array $fields, array $input): void
    {
        foreach ($fields as $field) {
            $fieldName = $field->getName();

            if (!$message->has($fieldName)) {
                // this means whatever was in the input never made it to the message.
                continue;
            }

            if (!isset($input[$fieldName])) {
                // the field in question doesn't exist in the input used to populate the message.
                // so whatever the value is was either a default or set by another process.
                continue;
            }

            // the input was used to populate the field on the message but they weren't allowed
            // to provide that field, only the server can set it.
            $message->clear($fieldName);
        }
    }

    /**
     * @param PbjxEvent $pbjxEvent
     * @param Message   $message
     * @param Request   $request
     */
    protected function bindConsoleApp(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has('ctx_app')
            || !$request->attributes->getBoolean('pbjx_console')
            || !$this->container->hasParameter('app_vendor')
        ) {
            return;
        }

        $app = AppV1::create()
            ->set('vendor', $this->container->getParameter('app_vendor') ?: null)
            ->set('name', $this->container->getParameter('app_name') . '-php.console')
            ->set('version', $this->container->getParameter('app_version') ?: null)
            ->set('build', $this->container->getParameter('app_build') ?: null);

        $message->set('ctx_app', $app);
    }

    /**
     * @param PbjxEvent $pbjxEvent
     * @param Message   $message
     * @param Request   $request
     */
    protected function bindCloud(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has('ctx_cloud') || !$this->container->hasParameter('cloud_provider')) {
            return;
        }

        $cloud = CloudV1::create()
            ->set('provider', $this->container->getParameter('cloud_provider') ?: null)
            ->set('region', $this->container->getParameter('cloud_region') ?: null)
            ->set('zone', $this->container->getParameter('cloud_zone') ?: null)
            ->set('instance_id', $this->container->getParameter('cloud_instance_id') ?: null)
            ->set('instance_type', $this->container->getParameter('cloud_instance_type') ?: null);

        $message->set('ctx_cloud', $cloud);
    }

    /**
     * @param PbjxEvent $pbjxEvent
     * @param Message   $message
     * @param Request   $request
     */
    protected function bindIp(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has('ctx_ip')) {
            return;
        }

        $message->set('ctx_ip', $request->getClientIp());
    }

    /**
     * @param PbjxEvent $pbjxEvent
     * @param Message   $message
     * @param Request   $request
     */
    protected function bindUserAgent(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has('ctx_ua')) {
            return;
        }

        $message->set('ctx_ua', $request->headers->get('User-Agent'));
    }

    /**
     * @return RequestStack
     */
    protected function getRequestStack(): RequestStack
    {
        if (null === $this->requestStack) {
            $this->requestStack = $this->container->get('request_stack');
        }

        return $this->requestStack;
    }

    /**
     * @return Request
     */
    protected function getCurrentRequest(): Request
    {
        return $this->getRequestStack()->getCurrentRequest() ?: new Request();
    }
}
