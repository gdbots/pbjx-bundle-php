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
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $locator = $container->getDefinition('gdbots_pbjx.service_locator');

        foreach ($container->findTaggedServiceIds('pbjx.handler') as $id => $attributes) {
            $def = $container->getDefinition($id);
            $def->setPublic(false)->setPrivate(true);

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
                    /** @var SchemaCurie $curie */
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

            // as of 2017-12-07 we don't deem this to be exception worthy
            /*
            if (empty($curies)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The service "%s" using pbjx.handler tag requires the "curie" attribute ' .
                        'or the class "%s" must implement "%s" and return at least one curie from handlesCuries method.',
                        $id,
                        $class,
                        $interface
                    )
                );
            }
            */

            foreach ($curies as $curie) {
                $args = [$curie->toString(), new ServiceClosureArgument(new Reference($id))];
                $locator->addMethodCall('registerHandler', $args);
            }
        }
    }
}
