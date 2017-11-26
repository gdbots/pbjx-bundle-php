<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @deprecated Our goal is to move all form functionality to the client (react/angular)
 *             and use server side validation with pbjx lifecycle events.
 */
class DateTimePickerType extends DatePickerType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('js_options', function (Options $options, $previousValue): array {
            return array_merge($previousValue, [
                'timePicker'          => true,
                'timePickerIncrement' => 15,
                'locale'              => ['format' => 'YYYY-MM-DDTHH:mm:ss.SSSSSS\Z'],
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
    public function getBlockPrefix()
    {
        return 'gdbots_pbjx_datetime_picker';
    }
}
