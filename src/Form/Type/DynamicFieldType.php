<?php

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\DataTransformer\DynamicFieldToArrayTransformer;
use Gdbots\Pbj\Enum\DynamicFieldKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class DynamicFieldType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new DynamicFieldToArrayTransformer());

        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[a-zA-Z_]{1}[a-zA-Z0-9_-]*$/'
                    ])
                ]
            ])
            ->add('kind', ChoiceType::class, [
                'choices' => DynamicFieldKind::values()
            ])
            ->add('value', HiddenType::class)
            ->add('bool_val', ChoiceType::class, [
                'choices' => [
                    'False' => false,
                    'True' => true
                ]
            ])
            ->add('date_val', DatePickerType::class)
            ->add('float_val', NumberType::class)
            ->add('int_val', IntegerType::class)
            ->add('string_val', TextType::class)
            ->add('text_val', TextareaType::class)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'compound' => true,
            'required' => false
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return FormType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'gdbots_pbjx_dynamic_field';
    }
}
