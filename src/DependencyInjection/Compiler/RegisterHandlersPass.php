<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Gdbots\Pbj\SchemaCurie;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Example use (services.yml)
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

            foreach ($attributes as $attribute) {
                if (!isset($attribute['curie'])) {
                    throw new \InvalidArgumentException(
                        sprintf('The service "%s" pbjx.handler tag requires the "curie" attribute.', $id)
                    );
                }

                $curie = SchemaCurie::fromString($container->getParameterBag()->resolveValue($attribute['curie']));
                $args = [$curie->toString(), new ServiceClosureArgument(new Reference($id))];
                $locator->addMethodCall('registerHandler', $args);
            }
        }
    }
}
