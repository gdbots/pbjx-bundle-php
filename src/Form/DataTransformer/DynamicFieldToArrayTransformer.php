<?php

namespace Gdbots\Bundle\PbjxBundle\Form\DataTransformer;

use Gdbots\Pbj\WellKnown\DynamicField;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class DynamicFieldToArrayTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (!empty($value)) {
            return DynamicField::fromArray($value);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (['name', 'kind', 'value'] != array_keys($value)) {
            throw new TransformationFailedException();
        }

        if (empty($value['name'])) {
            return null;
        }

        return [
            'name' => $value['name'],
            $value['kind'] => $value['value']
        ];
    }
}
