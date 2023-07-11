<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Gdbots\Pbj\Exception\NoMessageForMixin;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\DependencyInjection\PbjxHandler;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Preferred method in your app is to use autoconfigure let Symfony automatically
 * find the classes using the PbjxHandler interface and tag them.
 *
 * All your class will need to do is return an array of SchemaCurie objects.
 *
 * Example manual configuration...
 *
 * widgetco_blog.add_comment_handler:
 *   class: WidgetCo\Blog\AddCommentHandler
 *   public: false
 *   tags:
 *     - {name: pbjx.handler, curie: '%app_vendor%:blog:command:add-comment'}
 *
 * @link https://github.com/gdbots/pbjx-bundle-php/tree/beta#library-development
 *
 */
final class RegisterHandlersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $locator = $container->getDefinition('gdbots_pbjx.service_locator');

        foreach ($container->findTaggedServiceIds('pbjx.handler') as $id => $attributes) {
            $def = $container->getDefinition($id);
            $def->setPublic(false);

            if ($def->isAbstract()) {
                throw new \InvalidArgumentException(
                    sprintf('The service "%s" must not be abstract as pbjx request/command handlers are lazy-loaded.', $id)
                );
            }

            $curies = [];

            foreach ($attributes as $attribute) {
                if (!isset($attribute['curie'])) {
                    continue;
                }

                $curies[] = SchemaCurie::fromString($container->getParameterBag()->resolveValue($attribute['curie']));
            }

            $interface = PbjxHandler::class;
            /** @var PbjxHandler $class */
            $class = $container->getParameterBag()->resolveValue($def->getClass());
            if (is_subclass_of($class, $interface)) {
                try {
                    foreach ($class::handlesCuries() as $curie) {
                        $curies[] = $curie;
                    }
                } catch (NoMessageForMixin $e) {
                    // ignore, just means the app hasn't implemented anything
                    // using the mixin that this handler is designed for.
                } catch (\Throwable $e) {
                    throw $e;
                }
            }

            foreach ($curies as $curie) {
                $args = [(string)$curie, new ServiceClosureArgument(new Reference($id))];
                $locator->addMethodCall('registerHandler', $args);
            }
        }
    }
}
