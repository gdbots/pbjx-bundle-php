<?php

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimePickerType extends DatePickerType
{
    const NAME = 'gdbots_pbjx_datetime_picker';

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('js_options', function (Options $options, $previousValue) {
            return array_merge($previousValue, [
                'timePicker' => true,
                'timePickerIncrement' => 15,
                'locale' => ['format' => 'MM/DD/YYYY h:mm a']
            ]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return DateTimeType::class;
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
