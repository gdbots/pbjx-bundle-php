<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Gdbots\Schemas\Common\Enum\Trinary;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @deprecated Our goal is to move all form functionality to the client (react/angular)
 *             and use server side validation with pbjx lifecycle events.
 */
class TrinaryType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $values = [];
        foreach (Trinary::values() as $key => $value) {
            $values[sprintf('gdbots_pbjx.form.trinary.%s', strtolower($key))] = $value;
        }

        $resolver->setDefaults([
            'choices' => $values,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ChoiceType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'gdbots_pbjx_trinary';
    }
}
