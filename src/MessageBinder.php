<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\DependencyInjection\PbjxBinder;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Contexts\AppV1;
use Gdbots\Schemas\Contexts\CloudV1;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MessageBinder implements EventSubscriber, PbjxBinder
{
    protected ContainerInterface $container;
    protected ?RequestStack $requestStack = null;
    protected array $restrictedFields;

    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:command.bind' => ['bind', 10000],
            'gdbots:pbjx:mixin:event.bind'   => ['bind', 10000],
            'gdbots:pbjx:mixin:request.bind' => ['bind', 10000],
        ];
    }

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->restrictedFields = [
            'command_id',
            'ctx_causator',
            'ctx_causator_ref',
            'ctx_cloud',
            'ctx_correlator_ref',
            'ctx_ip',
            'ctx_ipv6',
            'ctx_tenant_id',
            'ctx_ua',
            'ctx_user_ref',
            'event_id',
            'occurred_at',
            'request_id',
            //'ctx_app',
            //'ctx_msg',
            //'ctx_retries',
            //'derefs',
            //'expected_etag',
        ];
    }

    public function bind(PbjxEvent $pbjxEvent): void
    {
        $message = $pbjxEvent->getMessage();
        $request = $this->getCurrentRequest();

        $restricted = !$request->attributes->getBoolean('pbjx_bind_unrestricted');
        $input = (array)$request->attributes->get('pbjx_input');

        if ($restricted) {
            $this->restrictBind($pbjxEvent, $message, $this->restrictedFields, $input);
        }

        $this->bindApp($pbjxEvent, $message, $request);
        $this->bindCloud($pbjxEvent, $message, $request);
        $this->bindIp($pbjxEvent, $message, $request);
        $this->bindUserAgent($pbjxEvent, $message, $request);
    }

    protected function restrictBind(PbjxEvent $pbjxEvent, Message $message, array $restrictedFields, array $input): void
    {
        foreach ($restrictedFields as $field) {
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
        if ($message->has('ctx_app') || !$this->container->hasParameter('app_vendor')) {
            return;
        }

        static $app = null;
        if (null === $app) {
            $name = $this->container->getParameter('app_name');
            if ($request->attributes->getBoolean('pbjx_console')) {
                $name .= '-php.console';
            }

            $app = AppV1::create()
                ->set('vendor', $this->container->getParameter('app_vendor'))
                ->set('name', $name)
                ->set('version', $this->container->getParameter('app_version') ?: null)
                ->set('build', $this->container->getParameter('app_build') ?: null);
        }

        $message->set('ctx_app', $app);
    }

    protected function bindCloud(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has('ctx_cloud') || !$this->container->hasParameter('cloud_provider')) {
            return;
        }

        static $cloud = null;
        if (null === $cloud) {
            $cloud = CloudV1::create()
                ->set('provider', $this->container->getParameter('cloud_provider') ?: null)
                ->set('region', $this->container->getParameter('cloud_region') ?: null)
                ->set('zone', $this->container->getParameter('cloud_zone') ?: null)
                ->set('instance_id', $this->container->getParameter('cloud_instance_id') ?: null)
                ->set('instance_type', $this->container->getParameter('cloud_instance_type') ?: null);
        }

        $message->set('ctx_cloud', $cloud);
    }

    protected function bindIp(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has('ctx_ip') || $message->has('ctx_ipv6')) {
            return;
        }

        $ip = (string)$request->getClientIp();
        if (empty($ip)) {
            return;
        }

        if (strpos($ip, ':') !== false) {
            $message->set('ctx_ipv6', $ip);
        } else {
            $message->set('ctx_ip', $ip);
        }
    }

    protected function bindUserAgent(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        if ($message->has('ctx_ua')) {
            return;
        }

        $message->set('ctx_ua', $request->headers->get('User-Agent'));
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
