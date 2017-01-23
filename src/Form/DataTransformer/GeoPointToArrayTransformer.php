<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Form\DataTransformer;

use Gdbots\Pbj\WellKnown\GeoPoint;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class GeoPointToArrayTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($value): ?GeoPoint
    {
        if (empty($value)) {
            return;
        }

        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        return GeoPoint::fromArray($value);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value): ?array
    {
        if (empty($value)) {
            return;
        }

        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        if (['latitude', 'longitude'] != array_keys($value)) {
            throw new TransformationFailedException();
        }

        return [
            'coordinates' => [
                $value['longitude'],
                $value['latitude'],
            ],
        ];
    }
}
