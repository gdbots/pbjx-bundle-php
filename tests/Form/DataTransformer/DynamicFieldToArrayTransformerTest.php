<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\DataTransformer\DynamicFieldToArrayTransformer;
use Gdbots\Pbj\Enum\DynamicFieldKind;
use Gdbots\Pbj\WellKnown\DynamicField;

class DynamicFieldToArrayTransformerTest extends \PHPUnit_Framework_TestCase
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
                ['name' => 'foo', 'bool_val' => true],
                DynamicField::fromArray(['name' => 'foo', 'bool_val' => true])
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
                ['name' => 'foo', 'kind' => 'bool_val', 'value' => true],
                ['name' => 'foo', 'bool_val' => true]
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
        $this->createTestTransfomer()->reverseTransform(['n' => 'foo', 'k' => 'bool_val', 'v' => true]);
    }

    /**
     * @return DynamicFieldToArrayTransformer
     */
    private function createTestTransfomer()
    {
        return new DynamicFieldToArrayTransformer();
    }
}
