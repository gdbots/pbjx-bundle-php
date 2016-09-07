<?php

namespace Gdbots\Bundle\PbjxBundle\Form\DataTransformer;

use Gdbots\Pbj\WellKnown\GeoPoint;
use Symfony\Component\Form\DataTransformerInterface;

class GeoPointToArrayTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (!empty($value)) {
            return GeoPoint::fromArray($value);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (count($value) != 2) {
            return null;
        }

        return [
            'type' => 'Point',
            'coordinates' => array_reverse(array_values($value))
        ];
    }
}
