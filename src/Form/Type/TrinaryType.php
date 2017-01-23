<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Gdbots\Schemas\Common\Enum\Trinary;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrinaryType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $values = [];
        foreach (Trinary::values() as $key => $value) {
            $values[sprintf('gdbots_pbjx.form.trinary.%s', strtolower($key))] = $value;
        }

        $resolver->setDefaults([
            'choices' => $values
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'gdbots_pbjx_trinary';
    }
}
