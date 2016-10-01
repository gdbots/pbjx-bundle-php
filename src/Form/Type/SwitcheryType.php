<?php

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SwitcheryType extends AbstractType
{
    /** @var array */
    protected $jsOptions = [
        'sw_color' => null,
        'sw_secondaryColor' => null,
        'sw_jackColor' => null,
        'sw_jackSecondaryColor' => null,
        'sw_className' => null,
        'sw_disabled' => null,
        'sw_disabledOpacity' => null,
        'sw_speed' => null,
        'sw_size' => null
    ];

    /**
     * @inheritDoc
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['js_options'] = $this->parseJsOptions(array_intersect_key($options, $this->jsOptions));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array_merge(
                [
                    'required' => false
                ],
                $this->jsOptions
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return CheckboxType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'gdbots_pbjx_switchery';
    }

    /**
     * @param array $options
     *
     * @return array
     */
    private function parseJsOptions(array $options)
    {
        // remove null settings
        $options = array_filter(
            $options,
            function ($val) {
                return ($val !== null);
            }
        );

        $parsedOptions = [];

        foreach ($options as $key => $value) {
            if (false !== strpos($key, 'sw_')) {
                // remove 'sw_' prefix
                $dpKey = substr($key, 3);

                $parsedOptions[$dpKey] = $value;
            }
        }

        return $parsedOptions;
    }
}
