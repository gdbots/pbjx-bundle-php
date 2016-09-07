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
        if ($value instanceof DynamicField) {
            return [
                'name' => $value->getName(),
                'kind' => $value->getKind(),
                'value' => $value->getValue()
            ];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        $dynamicFields = [];

        foreach ($value as $data) {
            if (['name', 'kind', 'value'] != array_keys($data)) {
                throw new TransformationFailedException();
            }

            if (array_key_exists($data['name'], $dynamicFields)) {
                throw new TransformationFailedException('Duplicate name detected');
            }

            $dynamicFields[$data['name']] = DynamicField::fromArray([
                'name' => $data['name'],
                $data['kind'] => $data['value']
            ]);
        }

        return $dynamicFields;
    }
}
