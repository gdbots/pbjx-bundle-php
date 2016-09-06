<?php

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FormCompilerPass implements CompilerPassInterface
{
    const GDBOTS_FORM_LAYOUT = 'GdbotsPbjxBundle:Form:fields.html.twig';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $allResources = $container->getParameter('twig.form.resources');

        if (!in_array(self::GDBOTS_FORM_LAYOUT, $allResources)) {
            $allResources[] = self::GDBOTS_FORM_LAYOUT;
        }

        $container->setParameter('twig.form.resources', $allResources);
    }
}
