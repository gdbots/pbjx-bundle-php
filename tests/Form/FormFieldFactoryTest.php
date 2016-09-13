<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Form;

use Gdbots\Bundle\PbjxBundle\Form\FormFieldFactory;
use Gdbots\Pbj\Field;
use Gdbots\Pbj\Type as T;

class FormFieldFactoryTest extends \PHPUnit_Framework_TestCase
{
    /** @var FormFieldFactory */
    private $formFieldFactory;

    protected function setUp()
    {
        parent::setUp();

        $this->formFieldFactory = new FormFieldFactory();
    }

    /**
     * @dataProvider supportsDataProvider
     *
     * @param string $name
     * @param T\Type $type
     * @param string $className
     */
    public function testSupports($name, T\Type $type, $className = null)
    {
        $pbjField = new Field($name, $type, null, false, null, null, null, null, null, null, 10, 2, null, true, $className);
        $this->assertTrue($this->formFieldFactory->supports($pbjField));
    }

    public function supportsDataProvider()
    {
        return [
            ['name' => 'big_int', 'type' => T\BigIntType::create()],
            //['name' => 'binary', 'type' => T\BinaryType::create()],
            //['name' => 'blob', 'type' => T\BlobType::create()],
            ['name' => 'boolean', 'type' => T\BooleanType::create()],
            ['name' => 'date', 'type' => T\DateType::create()],
            ['name' => 'date_time', 'type' => T\DateTimeType::create()],
            ['name' => 'decimal', 'type' => T\DecimalType::create()],
            ['name' => 'dynamic_field', 'type' => T\DynamicFieldType::create()],
            ['name' => 'float', 'type' => T\FloatType::create()],
            ['name' => 'geo_point', 'type' => T\GeoPointType::create()],
            ['name' => 'identifier', 'type' => T\IdentifierType::create(), 'className' => 'Gdbots\Pbj\WellKnown\UuidIdentifier'],
            ['name' => 'int', 'type' => T\IntType::create()],
            ['name' => 'int_enum', 'type' => T\IntEnumType::create(), 'className' => 'Gdbots\Schemas\Common\Enum\Month'],
            //['name' => 'medium_blob', 'type' => T\MediumBlobType::create()],
            ['name' => 'medium_int', 'type' => T\MediumIntType::create()],
            ['name' => 'medium_text', 'type' => T\MediumTextType::create()],
            //['name' => 'message', 'type' => T\MessageType::create(), 'className' => 'Gdbots\Schemas\Pbjx\Mixin\Request\Request'],
            ['name' => 'message_ref', 'type' => T\MessageRefType::create()],
            ['name' => 'microtime', 'type' => T\MicrotimeType::create()],
            ['name' => 'signed_big_int', 'type' => T\SignedBigIntType::create()],
            ['name' => 'signed_int', 'type' => T\SignedIntType::create()],
            ['name' => 'signed_medium_int', 'type' => T\SignedMediumIntType::create()],
            ['name' => 'signed_small_int', 'type' => T\SignedSmallIntType::create()],
            ['name' => 'signed_tiny_int', 'type' => T\SignedTinyIntType::create()],
            ['name' => 'small_int', 'type' => T\SmallIntType::create()],
            ['name' => 'string', 'type' => T\StringType::create()],
            ['name' => 'string_enum', 'type' => T\StringEnumType::create(), 'className' => 'Gdbots\Schemas\Ncr\Enum\NodeStatus'],
            ['name' => 'text', 'type' => T\TextType::create()],
            ['name' => 'time_uuid', 'type' => T\TimeUuidType::create()],
            ['name' => 'timestamp', 'type' => T\TimestampType::create()],
            ['name' => 'tiny_int', 'type' => T\TinyIntType::create()],
            ['name' => 'trinary', 'type' => T\TrinaryType::create()],
            ['name' => 'uuid', 'type' => T\UuidType::create()]
        ];
    }
}
