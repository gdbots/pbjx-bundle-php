<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Form\Type;

use Gdbots\Bundle\PbjxBundle\Form\Type\CollectionType;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionTypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CollectionType
     */
    protected $type;

    /**
     * Setup test env
     */
    protected function setUp()
    {
        $this->type = new CollectionType();
    }

    public function testBuildForm()
    {
        $builder = $this->getMock('Symfony\Component\Form\Test\FormBuilderInterface');

        $builder->expects($this->once())
            ->method('addEventSubscriber')
            ->with($this->isInstanceOf('Gdbots\Bundle\PbjxBundle\Form\EventListener\CollectionTypeSubscriber'));

        $options = [];
        $this->type->buildForm($builder, $options);
    }

    /**
     * @dataProvider buildViewDataProvider
     */
    public function testBuildView($options, $expectedVars)
    {
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $view = new FormView();

        $this->type->buildView($view, $form, $options);

        foreach ($expectedVars as $key => $val) {
            $this->assertArrayHasKey($key, $view->vars);
            $this->assertEquals($val, $view->vars[$key]);
        }
    }

    public function buildViewDataProvider()
    {
        return [
            [
                'options' => [
                    'show_form_when_empty' => false,
                    'prototype_name' => '__name__',
                    'row_count_add' => 1,
                    'row_count_initial' => 1
                ],
                'expectedVars' => [
                    'show_form_when_empty' => false,
                    'prototype_name' => '__name__',
                    'row_count_initial' => 1
                ]
            ],
            [
                'options' => [
                    'show_form_when_empty' => true,
                    'prototype_name' => '__custom_name__',
                    'row_count_add' => 1,
                    'row_count_initial' => 5
                ],
                'expectedVars' => [
                    'show_form_when_empty' => true,
                    'prototype_name' => '__custom_name__',
                    'row_count_initial' => 5
                ]
            ]
        ];
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     * @expectedExceptionMessage The required option "entry_type" is missing.
     */
    public function testConfigureOptionsWithoutType()
    {
        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);
        $resolver->resolve([]);
    }

    public function testConfigureOptions()
    {
        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);

        $options = [
            'entry_type' => 'test_type'
        ];
        $resolvedOptions = $resolver->resolve($options);
        $this->assertEquals(
            [
                'entry_type' => 'test_type',
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'prototype_name' => '__name__',
                'show_form_when_empty' => true,
                'row_count_add' => 1,
                'row_count_initial' => 1
            ],
            $resolvedOptions
        );
    }

    public function testConfigureOptionsDisableAdd()
    {
        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);

        $options = [
            'entry_type' => 'test_type',
            'allow_add' => false
        ];
        $resolvedOptions = $resolver->resolve($options);
        $this->assertEquals(
            [
                'entry_type' => 'test_type',
                'allow_add' => false,
                'allow_delete' => true,
                'prototype' => true,
                'prototype_name' => '__name__',
                'show_form_when_empty' => false,
                'row_count_add' => 1,
                'row_count_initial' => 1
            ],
            $resolvedOptions
        );
    }

    public function testConfigureOptionsDisableShowFormWhenEmpty()
    {
        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);

        $options = [
            'entry_type' => 'test_type',
            'show_form_when_empty' => false
        ];
        $resolvedOptions = $resolver->resolve($options);
        $this->assertEquals(
            [
                'entry_type' => 'test_type',
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'prototype_name' => '__name__',
                'show_form_when_empty' => false,
                'row_count_add' => 1,
                'row_count_initial' => 1
            ],
            $resolvedOptions
        );
    }

    public function testGetParent()
    {
        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\CollectionType', $this->type->getParent());
    }

    public function testGetBlockPrefix()
    {
        $this->assertEquals('gdbots_pbjx_collection', $this->type->getBlockPrefix());
    }

    /**
     * @return OptionsResolver
     */
    protected function getOptionsResolver()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([]);

        return $resolver;
    }
}
