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
 *   public: true
 *   tags:
 *     - {name: pbjx.handler, alias: '%app_vendor%_blog.add_comment_handler'}
 *
 * @link https://github.com/gdbots/pbjx-bundle-php/tree/beta#library-development
 *
 */
// todo: figure out a way to lazy load handlers and have them as private services
// only the ContainerAwareServiceLocator needs to know about them but they
// MUST be lazy loaded and support this aliasing pass
final class AliasHandlersPass implements CompilerPassInterface
{
    /** @var string */
    private $handlerTag = 'pbjx.handler';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds($this->handlerTag) as $id => $attributes) {
            $def = $container->getDefinition($id);
            $def->setPublic(false)->setPrivate(true);

            if (!$def->isPublic()) {
//                throw new \InvalidArgumentException(
//                    sprintf('The service "%s" must be public as pbjx request/command handlers are lazy-loaded.', $id)
//                );
            }

            if ($def->isAbstract()) {
                throw new \InvalidArgumentException(
                    sprintf('The service "%s" must not be abstract as pbjx request/command handlers are lazy-loaded.', $id)
                );
            }

            $locator = $container->getDefinition('gdbots_pbjx.service_locator');

            foreach ($attributes as $attribute) {

                if (isset($attribute['curie'])) {
                    $curie = SchemaCurie::fromString($container->getParameterBag()->resolveValue($attribute['curie']));
                    $args = [$curie->toString(), new ServiceClosureArgument(new Reference($id))];
                    $locator->addMethodCall('registerHandler', $args);
                }

                if (!isset($attribute['alias'])) {
                    continue;
                }

                $alias = $container->getParameterBag()->resolveValue($attribute['alias']);
                $container->removeDefinition($id);

                if ($container->hasDefinition($alias) || $container->hasAlias($alias)) {
                    continue;
                }

                $container->setDefinition($alias, $def);
            }
        }
    }
}
