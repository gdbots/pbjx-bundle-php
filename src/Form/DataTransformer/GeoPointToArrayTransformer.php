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
        if ($value instanceof GeoPoint) {
            return $value->toArray();
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        return GeoPoint::fromArray([
            'coordinates' => [
                $value['longitude'],
                $value['latitude']
            ]
        ]);
    }
}
