<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbjx\DependencyInjection\PbjxBinder;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CommandBinder implements EventSubscriber, PbjxBinder
{
    use MessageBinderTrait;

    private array $restrictedFields;

    public static function getSubscribedEvents()
    {
        return [
            CommandV1Mixin::SCHEMA_CURIE . '.bind' => ['bind', 10000],
        ];
    }

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->restrictedFields = array_diff(CommandV1Mixin::FIELDS, ['ctx_app', 'ctx_retries', 'expected_etag']);
    }

    public function bind(PbjxEvent $pbjxEvent): void
    {
        $message = $pbjxEvent->getMessage();
        $request = $this->getCurrentRequest();

        $restricted = !$request->attributes->getBoolean('pbjx_bind_unrestricted');
        $input = (array)$request->attributes->get('pbjx_input');

        if ($restricted) {
            $this->restrictBindFromInput($pbjxEvent, $message, $this->restrictedFields, $input);
        }

        $this->bindApp($pbjxEvent, $message, $request);
        $this->bindCloud($pbjxEvent, $message, $request);
        $this->bindIp($pbjxEvent, $message, $request);
        $this->bindUserAgent($pbjxEvent, $message, $request);
    }
}
