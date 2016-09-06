<?php

namespace Gdbots\Bundle\PbjxBundle\Form\DataTransformer;

use Gdbots\Pbj\WellKnown\GeoPoint;
use Symfony\Component\Form\DataTransformerInterface;

class GeoPointToStringTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        return $value ? $value->toString() : null;
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
