<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\Type\DateTimePickerType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\TypeTestCase;

class DateTimePickerTypeTest extends TypeTestCase
{
    /**
     * @var DateTimePickerType
     */
    private $type;

    protected function setUp()
    {
        parent::setUp();

        $this->type = new DateTimePickerType();
    }

    public function testGetBlockPrefix()
    {
        $this->assertEquals('gdbots_pbjx_datetime_picker', $this->type->getBlockPrefix());
    }

    public function testGetParent()
    {
        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\DateTimeType', $this->type->getParent());
    }

    public function testConfigureOptions()
    {
        $expectedOptions = [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'group_icon' => null,
            'clear_label' => null,
            'clear_icon' => null,
            'js_options' => [
                'singleDatePicker' => true,
                'autoApply' => true,
                'timePicker' => true,
                'timePickerIncrement' => 15,
                'locale' => ['format' => 'MM/DD/YYYY h:mm a'],
                'opens' => 'left',
                'applyClass' => 'bg-slate',
                'cancelClass' => 'btn-default'
            ],
            'js_callback' => 'function(start, end, label) {}'
        ];

        $form = $this->factory->create(get_class($this->type));
        $form->submit((new \DateTime()));

        $options = $form->getConfig()->getOptions();
        foreach ($expectedOptions as $name => $expectedValue) {
            $this->assertArrayHasKey($name, $options);
            $this->assertEquals($expectedValue, $options[$name]);
        }
    }

    /**
     * @dataProvider optionsDataProvider
     *
     * @param array  $options
     * @param string $expectedKey
     * @param mixed  $expectedValue
     */
    public function testFinishView($options, $expectedKey, $expectedValue)
    {
        $form = $this->getMockBuilder('Symfony\Component\Form\Form')
            ->disableOriginalConstructor()->getMock();

        $view = new FormView();
        $this->type->finishView($view, $form, $options);
        $this->assertArrayHasKey($expectedKey, $view->vars);
        $this->assertEquals($expectedValue, $view->vars[$expectedKey]);
    }

    public function optionsDataProvider()
    {
        return [
            [
                ['js_callback' => 'function(start, end, label) {}'],
                'js_callback',
                'function(start, end, label) {}'
            ],
            [
                ['js_options' => ['locale' => ['format' => 'MM/DD/YYYY h:mm a']]],
                'js_options',
                ['locale' => ['format' => 'MM/DD/YYYY h:mm a']]
            ],
        ];
    }

    /**
     * @dataProvider valuesDataProvider
     *
     * @param string    $value
     * @param \DateTime $expectedValue
     */
    public function testSubmitValidData($value, $expectedValue)
    {
        $form = $this->factory->create(get_class($this->type));
        $form->submit($value);
        $this->assertDateTimeEquals($expectedValue, $form->getData());
    }

    public function valuesDataProvider()
    {
        return [
            [
                '2002-10-02T15:00:00+00:00',
                new \DateTime('2002-10-02T15:00:00+00:00')
            ],
            [
                '2002-10-02T15:00:00Z',
                new \DateTime('2002-10-02T15:00:00Z')
            ],
            [
                '2002-10-02T15:00:00.05Z',
                new \DateTime('2002-10-02T15:00:00.05Z')
            ]
        ];
    }
}
