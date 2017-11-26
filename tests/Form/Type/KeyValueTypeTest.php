<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\Type\CollectionType;
use Gdbots\Bundle\PbjxBundle\Form\Type\KeyValueType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Test\TypeTestCase;

class KeyValueTypeTest extends TypeTestCase
{
    /** @var KeyValueType */
    private $type;

    protected function setUp()
    {
        parent::setUp();

        $this->type = new KeyValueType();
    }

    public function testGetBlockPrefix()
    {
        $this->assertEquals('gdbots_pbjx_key_value', $this->type->getBlockPrefix());
    }

    public function testGetParent()
    {
        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\FormType', $this->type->getParent());
    }

    public function testSubmitValidData()
    {
        $form = $this->factory->create(
            get_class($this->type),
            null,
            [
                'value_type' => TextType::class,
            ]
        );

        $form->submit([
            'key'   => 'key1',
            'value' => 'string-value',
        ]);

        $this->assertTrue($form->isValid());
        $this->assertSame(['key1' => 'string-value'], $form->getData());
    }

    public function testWithChoiceType()
    {
        $obj1 = (object)[
            'id'   => 1,
            'name' => 'choice1',
        ];

        $form = $this->factory->create(
            get_class($this->type),
            null,
            [
                'value_type'    => ChoiceType::class,
                'value_options' => [
                    'choices'      => [$obj1],
                    'choice_value' => 'id',
                    'choice_name'  => 'name',
                ],
            ]
        );

        $form->submit([
            'key'   => 'key1',
            'value' => '1',
        ]);

        $this->assertTrue($form->isValid());
        $this->assertSame(['key1' => $obj1], $form->getData());
    }

    public function testWithCustomKeyType()
    {
        $form = $this->factory->create(
            get_class($this->type),
            null,
            [
                'key_type'   => CountryType::class,
                'value_type' => IntegerType::class,
            ]
        );

        $form->submit([
            'key'   => 'US',
            'value' => '1',
        ]);

        $this->assertTrue($form->isValid());
        $this->assertSame(['US' => 1], $form->getData());
    }

    public function testWithCollectionKeyType()
    {
        $form = $this->factory->create(
            CollectionType::class,
            null,
            [
                'entry_type'    => get_class($this->type),
                'entry_options' => [
                    'value_type'    => ChoiceType::class,
                    'value_options' => [
                        'choices' => ['US', 'GB'],
                    ],
                ],
            ]
        );

        $form->submit([
            [
                'key'   => 'state',
                'value' => 'US',
            ],
            [
                'key'   => 'state',
                'value' => 'GB',
            ],
        ]);

        $this->assertTrue($form->isValid());
        $this->assertSame([['state' => 'US'], ['state' => 'GB']], $form->getData());
    }
}
