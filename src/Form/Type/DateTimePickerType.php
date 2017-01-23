<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimePickerType extends DatePickerType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('js_options', function (Options $options, $previousValue) {
            return array_merge($previousValue, [
                'timePicker' => true,
                'timePickerIncrement' => 15,
                'locale' => ['format' => 'YYYY-MM-DDTHH:mm:ss.SSSSSS\Z']
            ]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): string
    {
        return DateTimeType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'gdbots_pbjx_datetime_picker';
    }
}
