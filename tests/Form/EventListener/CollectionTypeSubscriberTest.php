<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Form\EventListener;

use Gdbots\Bundle\PbjxBundle\Form\EventListener\CollectionTypeSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Test\FormInterface;

class CollectionTypeSubscriberTest extends TestCase
{
    /**
     * @var CollectionTypeSubscriber
     */
    protected $subscriber;

    /**
     * SetUp test environment
     */
    protected function setUp()
    {
        $this->subscriber = new CollectionTypeSubscriber();
    }

    public function testGetSubscribedEvents()
    {
        $result = $this->subscriber->getSubscribedEvents();

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey(FormEvents::PRE_SUBMIT, $result);
    }

    /**
     * @dataProvider preSubmitNoDataDataProvider
     *
     * @param array|null $data
     */
    public function testPreSubmitNoData($data)
    {
        $event = $this->getMockBuilder('Symfony\Component\Form\FormEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getData')
            ->will($this->returnValue($data));
        $event->expects($this->never())
            ->method('setData');

        $this->subscriber->preSubmit($event);
    }

    /**
     * @return array
     */
    public function preSubmitNoDataDataProvider()
    {
        return [
            [null, []],
            [[], []],
        ];
    }

    /**
     * @dataProvider preSubmitDataProvider
     *
     * @param array $data
     * @param array $expected
     */
    public function testPreSubmit(array $data, array $expected)
    {
        $form = $this->getMockBuilder('Symfony\Component\Form\Test\FormInterface')->getMock();
        $event = $this->createEvent($data, $form);
        $this->subscriber->preSubmit($event);
        $this->assertEquals($expected, $event->getData());
    }

    public function preSubmitDataProvider()
    {
        return [
            'simple_data_array'     => [
                'data'     => [['k' => 'v']],
                'expected' => [['k' => 'v']],
            ],
            'skip_empty_data_array' => [
                'data'     => [[[]], [], ['k' => 'v'], []],
                'expected' => ['2' => ['k' => 'v']],
            ],
        ];
    }

    /**
     * @dataProvider preSubmitNoResetDataProvider
     *
     * @param array $data
     */
    public function testPreSubmitNoReset($data)
    {
        $event = $this->getMockBuilder('Symfony\Component\Form\FormEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getData')
            ->will($this->returnValue($data));
        $event->expects($this->never())
            ->method('setData');

        $this->subscriber->preSubmit($event);
    }

    /**
     * @return array
     */
    public function preSubmitNoResetDataProvider()
    {
        return [
            [[]],
            ['foo'],
        ];
    }

    /**
     * @param mixed              $data
     * @param FormInterface|null $form
     *
     * @return FormEvent
     */
    protected function createEvent($data, FormInterface $form = null)
    {
        $form = $form ? $form : $this->getMockBuilder('Symfony\Component\Form\Test\FormInterface')->getMock();
        return new FormEvent($form, $data);
    }
}
