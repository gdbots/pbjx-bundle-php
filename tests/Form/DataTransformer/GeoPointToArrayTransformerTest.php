<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\DataTransformer\GeoPointToArrayTransformer;
use Gdbots\Pbj\WellKnown\GeoPoint;

class GeoPointToArrayTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider transformDataProvider
     *
     * @param mixed $value
     * @param mixed $expectedValue
     */
    public function testTransform($value, $expectedValue)
    {
        $transformer = $this->createTestTransfomer();
        $this->assertEquals($expectedValue, $transformer->transform($value));
    }

    public function transformDataProvider()
    {
        return array(
            'default' => [
                ['coordinates' => [1, 2]],
                GeoPoint::fromArray(['coordinates' => [1, 2]])
            ],
            'null' => [
                null,
                null
            ],
        );
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "array", "string" given
     */
    public function testTransformFailsWhenUnexpectedType()
    {
        $transformer = $this->createTestTransfomer();
        $transformer->transform('string');
    }

    /**
     * @dataProvider reverseTransformDataProvider
     *
     * @param mixed $value
     * @param mixed $expectedValue
     */
    public function testReverseTransform($value, $expectedValue)
    {
        $transformer = $this->createTestTransfomer();
        $this->assertEquals($expectedValue, $transformer->reverseTransform($value));
    }

    public function reverseTransformDataProvider()
    {
        return array(
            'default' => [
                ['latitude' => 1, 'longitude' => 2],
                ['coordinates' => [2, 1]]
            ],
            'null' => [
                null,
                null
            ],
            'empty' => array(
                [],
                null
            ),
        );
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "array", "string" given
     */
    public function testReverseTransformFailsWhenUnexpectedType()
    {
        $this->createTestTransfomer()->reverseTransform('string');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformFailsWhenTransformationFailed()
    {
        $this->createTestTransfomer()->reverseTransform(['lng' => 1, 'lon' => 2]);
    }

    /**
     * @return GeoPointToArrayTransformer
     */
    private function createTestTransfomer()
    {
        return new GeoPointToArrayTransformer();
    }
}
