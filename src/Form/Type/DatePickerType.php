<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @link       https://github.com/dangrossman/bootstrap-daterangepicker
 *
 * @deprecated Our goal is to move all form functionality to the client (react/angular)
 *             and use server side validation with pbjx lifecycle events.
 */
class DatePickerType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, [
            'group_icon'  => null,
            'clear_label' => null,
            'clear_icon'  => null,
            'js_options'  => null,
            'js_callback' => null,
        ]);

        foreach (array_keys($view->vars) as $key) {
            if (isset($options[$key]) && !empty($options[$key])) {
                $view->vars[$key] = $options[$key];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'model_timezone' => 'UTC',
            'view_timezone'  => 'UTC',
            'widget'         => 'single_text',
            'group_icon'     => null,
            'clear_label'    => null,
            'clear_icon'     => null,
            'js_options'     => [
                'singleDatePicker' => true,
                'autoApply'        => true,
                'locale'           => ['format' => 'YYYY-MM-DD'],
                'opens'            => 'left',
                'applyClass'       => 'bg-slate',
                'cancelClass'      => 'btn-default',
            ],
            'js_callback'    => 'function(start, end, label) {}',
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
    public function getBlockPrefix()
    {
        return 'gdbots_pbjx_date_picker';
    }
}
