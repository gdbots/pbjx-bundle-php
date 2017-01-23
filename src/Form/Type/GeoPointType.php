<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\DataTransformer\GeoPointToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class GeoPointType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new GeoPointToArrayTransformer());

        $builder
            ->add('latitude', NumberType::class, [
                'required' => $options['required'],
                'attr' => [
                    'pattern' => '^-?\d*(\.\d+)?$'
                ],
                'constraints' => [
                    new Length([
                        'min' => -90,
                        'max' => 90
                    ])
                ]
            ])
            ->add('longitude', NumberType::class, [
                'required' => $options['required'],
                'attr' => [
                    'pattern' => '^-?\d*(\.\d+)?$'
                ],
                'constraints' => [
                    new Length([
                        'min' => -180,
                        'max' => 180
                    ])
                ]
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
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
        return 'gdbots_pbjx_geo_point';
    }
}
