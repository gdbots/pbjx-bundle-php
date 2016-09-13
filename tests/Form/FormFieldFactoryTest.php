<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Form;

use Gdbots\Bundle\PbjxBundle\Form\FormFieldFactory;
use Gdbots\Bundle\PbjxBundle\Form\FormField;
use Gdbots\Bundle\PbjxBundle\Form\Type;
use Gdbots\Pbj\Enum\Format;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\Field;
use Gdbots\Pbj\Type as T;
use Symfony\Component\Form\Extension\Core\Type as sfT;

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
        $fb = Fb::create($name, $type);

        if ($className) {
            $fb->className($className);
        }

        $this->assertTrue($this->formFieldFactory->supports($fb->build()));
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

    /**
     * @dataProvider createDataProvider
     *
     * @param Field  $pbjField
     * @param string $type
     * @param array  $options
     */
    public function testCreate(Field $pbjField, $type, array $options = [])
    {
        $formField = $this->formFieldFactory->create($pbjField);
        $formFieldOptions = $formField->getOptions();

        $this->assertEquals($type, $formField->getType());

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $this->assertTrue(isset($formFieldOptions[$key]));

                foreach ($value as $k => $v) {
                    $this->assertEquals($v, $formFieldOptions[$key][$k]);
                }

                continue;
            }

            $this->assertEquals($value, $formFieldOptions[$key]);
        }
    }

    public function createDataProvider()
    {
        return [
            [
                'pbjField' => Fb::create('string', T\StringType::create())->build(),
                'type' => sfT\TextType::class
            ],
            [
                'pbjField' => Fb::create('boolean', T\BooleanType::create())->build(),
                'type' => Type\SwitcheryType::class
            ],
            [
                'pbjField' => Fb::create('emails', T\StringType::create())
                                ->format(Format::EMAIL())
                                ->asAMap()
                                ->build(),
                'type' => Type\CollectionType::class,
                'options' => [
                    'entry_type' => Type\KeyValueType::class,
                    'entry_options' => [
                        'value_type' => sfT\EmailType::class
                    ]
                ]
            ]
        ];
    }
}
