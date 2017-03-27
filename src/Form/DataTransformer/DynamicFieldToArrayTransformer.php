<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\Form\DataTransformer;

use Gdbots\Pbj\WellKnown\DynamicField;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class DynamicFieldToArrayTransformer implements DataTransformerInterface
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

        return DynamicField::fromArray($value);
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

        if (count(array_intersect(['name', 'kind', 'value'], array_keys($value))) !== 3) {
            throw new TransformationFailedException();
        }

        if (empty($value['name'])) {
            return null;
        }

        return [
            'name'         => $value['name'],
            $value['kind'] => $value['value'],
        ];
    }
}
