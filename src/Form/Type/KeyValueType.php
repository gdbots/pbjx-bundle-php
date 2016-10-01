<?php

namespace Gdbots\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\DataTransformer\KeyValueToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class KeyValueType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new KeyValueToArrayTransformer());

        if (null === $options['allowed_keys']) {
            if (!isset($options['key_options']['constraints'])) {
                $options['key_options']['constraints'] = [];
            }

            $options['key_options']['constraints'][] = new Regex([
                'pattern' => '/^[a-zA-Z_]{1}[a-zA-Z0-9_-]*$/'
            ]);

            if (!isset($options['key_options']['attr'])) {
                $options['key_options']['attr'] = [];
            }

            $options['key_options']['attr']['pattern'] = '^[a-zA-Z_]{1}[a-zA-Z0-9_-]*$';

            $builder->add('key', $options['key_type'], $options['key_options']);
        } else {
            $builder->add('key', 'choice', array_merge(
                ['choice_list' => new SimpleChoiceList($options['allowed_keys'])],
                $options['key_options']
            ));
        }

        $builder->add('value', $options['value_type'], $options['value_options']);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'key_type' => TextType::class,
            'key_options' => [],
            'value_options' => [],
            'allowed_keys' => null
        ]);

        $resolver->setRequired(['value_type']);
        $resolver->setAllowedTypes('allowed_keys', ['null', 'array']);
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
        return 'gdbots_pbjx_key_value';
    }
}
