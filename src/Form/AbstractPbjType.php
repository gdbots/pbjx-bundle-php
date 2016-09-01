<?php

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Pbj\Field;
use Gdbots\Pbj\Schema;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Response\ResponseV1Mixin;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

abstract class AbstractPbjType extends AbstractType implements PbjFormType, DataMapperInterface
{
    /** @var FormFieldFactory */
    private $formFieldFactory;

    /**
     * Array of field names that shouldn't be added to any symfony form types.
     * For example, ctx_ip field is not something you'd allow a user to populate
     * as a separate process is responsible for binding that value.
     *
     * @var string[]
     */
    private static $ignoredFields;

    /**
     * Maps properties of some data to a list of forms.
     *
     * @param mixed           $data  Structured data.
     * @param FormInterface[] $forms A list of {@link FormInterface} instances.
     */
    public function mapDataToForms($data, $forms)
    {
        return;

        $schema = static::pbjSchema();
        $forms = iterator_to_array($forms);

        foreach ((array)$data as $k => $v) {
            if (!isset($forms[$k])) {
                continue;
            }

            $forms[$k]->setData($v);
        }

        foreach ($forms as $form) {
            if (!$form instanceof FormInterface || $form->isSubmitted() || $form->getConfig()->getDataLocked()) {
                continue;
            }

            if (null !== $form->getData()) {
                continue;
            }

            if (!$schema->hasField($form->getName())) {
                continue;
            }

            $pbjField = $schema->getField($form->getName());
            $form->setData($pbjField->getDefault());
        }
    }

    /**
     * Maps the data of a list of forms into the properties of some data.
     *
     * @param FormInterface[] $forms A list of {@link FormInterface} instances.
     * @param mixed           $data  Structured data.
     */
    public function mapFormsToData($forms, &$data)
    {
        $schema = static::pbjSchema();

        /*
         * if the only not null value is the "_schema" field then
         * we don't need this object.
         */
        $emptyCheck = array_filter($data, function ($item) use ($schema) {
            if (null === $item) {
                return false;
            }

            if ($item === $schema->getId()->toString()) {
                return false;
            }

            return true;
        });

        if (empty($emptyCheck)) {
            $data = [];
            return;
        }

        foreach ($forms as $form) {
            if (!$schema->hasField($form->getName())) {
                continue;
            }

            $pbjField = $schema->getField($form->getName());
            // fixme: need proper type encoding to array so this can be used to create the message
            $data[$pbjField->getName()] = $form->getData() ?: $pbjField->getDefault();
        }
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @param array $ignoredFields
     */
    final protected function buildPbjForm(FormBuilderInterface $builder, array $options, array $ignoredFields = [])
    {
        $schema = static::pbjSchema();
        $builder->setDataMapper($this);
        $ignoredFields = array_merge(array_flip($ignoredFields), self::getIgnoredFields());
        $factory = $this->getFormFieldFactory();

        foreach ($schema->getFields() as $pbjField) {
            $fieldName = $pbjField->getName();
            if (isset($ignoredFields[$fieldName])) {
                continue;
            }

            if (!$factory->supports($pbjField)) {
                continue;
            }

            $formField = $factory->create($pbjField);
            $child = $builder->create($formField->getName(), $formField->getType(), $formField->getOptions());

            if (isset($options['data'][$fieldName])) {
                $child->setData($options['data'][$fieldName]);
            } else {
                // fixme: type encoding here
                $child->setData($pbjField->getDefault());
            }

            $builder->add($child);
        }
    }

    /**
     * @return FormFieldFactory
     */
    final protected function getFormFieldFactory()
    {
        if (null === $this->formFieldFactory) {
            $this->formFieldFactory = new FormFieldFactory();
        }

        return $this->formFieldFactory;
    }

    /**
     * @return string[]
     */
    private static function getIgnoredFields()
    {
        if (null !== self::$ignoredFields) {
            return self::$ignoredFields;
        }

        /** @var Field $field */
        foreach (CommandV1Mixin::create()->getFields() as $field) {
            self::$ignoredFields[$field->getName()] = true;
        }

        foreach (EventV1Mixin::create()->getFields() as $field) {
            self::$ignoredFields[$field->getName()] = true;
        }

        foreach (RequestV1Mixin::create()->getFields() as $field) {
            self::$ignoredFields[$field->getName()] = true;
        }

        foreach (ResponseV1Mixin::create()->getFields() as $field) {
            self::$ignoredFields[$field->getName()] = true;
        }

        return self::$ignoredFields;
    }
}
