<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Compiler pass that automatically creates alias for pbjx handlers
 * for concrete services IF they are not defined by the application.
 *
 * wth does that mean?
 *
 * Example:
 * - You created a pbj mixin called "acme:blog:mixin:add-comment"
 * - You created a handler called "Acme\Blog\AddCommentHandler"
 * - A consumer of your pbj mixin and "AcmeBlogBundle" still doesn't have comments handled.
 *
 * - A consumer/app creates a concrete schema called "company:blog:command:add-comment"
 * - When Pbjx goes to "pbjx->send" the command, it will look for a handler called: "company_blog.add_comment_handler"
 * - That service doesn't exist so you'll get a "HandlerNotFound" exception with message:
 *      "ServiceLocator did not find a handler for curie [company:blog:command:add-comment]"
 *
 * This compiler pass allows a library developer to automatically
 * handle both its original service id (likely never called directly
 * unless decorated) AND your concrete (the real namespace) service.
 *
 * This is made possible by aliasing itself to the symfony service tag
 * provided, if the alias cannot be found in the container already.
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
                    return;
                }

                $container->setAlias($alias, $id);
            }
        }
    }
}
