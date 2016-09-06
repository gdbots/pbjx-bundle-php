<?php

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see `https://github.com/dangrossman/bootstrap-daterangepicker` for documentation
 */
class DatePickerType extends AbstractType
{
    const NAME = 'gdbots_pbjx_date_picker';

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if (!empty($options['js_options'])) {
            $view->vars['js_options'] = $options['js_options'];
        }

        if (!empty($options['js_callback'])) {
            $view->vars['js_callback'] = $options['js_callback'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'widget' => 'single_text',
            'js_options' => [
                'singleDatePicker' => true,
                'autoApply' => true,
                'locale' => ['format' => 'MM/DD/YYYY'],
                'opens' => 'left',
                'applyClass' => 'bg-slate',
                'cancelClass' => 'btn-default'
            ],
            'js_callback' => 'function(start, end, label) {}'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return DateType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::NAME;
    }
}
