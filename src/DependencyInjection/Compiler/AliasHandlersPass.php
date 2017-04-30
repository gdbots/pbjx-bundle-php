<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Example use (services.yml)
 *
 * widgetco_blog.add_comment_handler:
 *   class: WidgetCo\Blog\AddCommentHandler
 *     tags:
 *       - {name: pbjx.handler, alias: '%app_vendor%_blog.add_comment_handler'}
 *
 * @link https://github.com/gdbots/pbjx-bundle-php/tree/beta#library-development
 *
 */
class AliasHandlersPass implements CompilerPassInterface
{
    /** @var string */
    protected $handlerTag = 'pbjx.handler';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds($this->handlerTag) as $id => $attributes) {
            $def = $container->getDefinition($id);

            if (!$def->isPublic()) {
                throw new \InvalidArgumentException(
                    sprintf('The service "%s" must be public as pbjx request/command handlers are lazy-loaded.', $id)
                );
            }

            if ($def->isAbstract()) {
                throw new \InvalidArgumentException(
                    sprintf('The service "%s" must not be abstract as pbjx request/command handlers are lazy-loaded.', $id)
                );
            }

            foreach ($attributes as $attribute) {
                if (!isset($attribute['alias'])) {
                    continue;
                }

                $alias = $container->getParameterBag()->resolveValue($attribute['alias']);
                if ($container->hasDefinition($alias) || $container->hasAlias($alias)) {
                    continue;
                }

                $container->setAlias($alias, $id);
            }
        }
    }
}
