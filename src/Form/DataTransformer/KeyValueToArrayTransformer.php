<?php

namespace Gdbots\Bundle\PbjxBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class KeyValueToArrayTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (empty($value)) {
            return null;
        }

        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        return [
            'key' => array_keys($value)[0],
            'value' => array_values($value)[0]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (empty($value)) {
            return null;
        }

        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        if (count(array_intersect(['key', 'value'], array_keys($value))) !== 2) {
            throw new TransformationFailedException();
        }

        if (empty($value['key'])) {
            return null;
        }

        return [
            $value['key'] => $value['value']
        ];
    }
}
