<?php

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\EventListener\CollectionTypeSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType as BaseCollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(
            new CollectionTypeSubscriber()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, [
            'show_form_when_empty' => $options['show_form_when_empty'],
            'prototype_name' => $options['prototype_name'],
            'row_count_add' => $options['row_count_add'],
            'row_count_initial' => $options['row_count_initial'],
            'add_label' => $options['add_label'],
            'add_icon' => $options['add_icon'],
            'remove_label' => $options['remove_label'],
            'remove_icon' => $options['remove_icon']
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => true,
            'prototype_name' => '__name__',
            'show_form_when_empty' => true,
            'row_count_add' => 1,
            'row_count_initial' => 1,
            'add_label' => null,
            'add_icon' => null,
            'remove_label' => null,
            'remove_icon' => null
        ]);

        $resolver->setRequired(['entry_type']);

        $resolver->setNormalizer('show_form_when_empty', function (Options $options, $value) {
            return !$options['allow_add'] ? false : $value;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return BaseCollectionType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'gdbots_pbjx_collection';
    }
}
