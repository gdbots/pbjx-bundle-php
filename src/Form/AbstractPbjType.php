<?php

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Pbj\Enum\FieldRule;
use Gdbots\Pbj\Field;
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
        $schema = static::pbjSchema();
        $forms = iterator_to_array($forms);

        foreach ((array)$data as $k => $v) {
            if (!isset($forms[$k])) {
                continue;
            }

            $forms[$k]->setData($v);
        }

        foreach ($forms as $form) {
            $fieldName = $form->getName();
            if (!$form instanceof FormInterface || $form->isSubmitted() || $form->getConfig()->getDataLocked()) {
                continue;
            }

            if (null !== $form->getData()) {
                continue;
            }

            if (!$schema->hasField($fieldName)) {
                continue;
            }

            $this->setFormDefault($form, $schema->getField($fieldName));
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
        $isRoot = true;

        foreach ($forms as $form) {
            $isRoot = $form->isRoot();
            $fieldName = $form->getName();
            if (!$schema->hasField($fieldName)) {
                continue;
            }

            $pbjField = $schema->getField($fieldName);
            $value = $form->getData();

            if ($this->isEmpty($value) && !$pbjField->isRequired()) {
                continue;
            }

            if (null !== $value) {
                $data[$fieldName] = $value;
                continue;
            }

            switch ($pbjField->getRule()->getValue()) {
                case FieldRule::A_SINGLE_VALUE:
                    $data[$fieldName] = $value;
                    break;

                case FieldRule::A_SET:
                case FieldRule::A_LIST:
                    $data[$fieldName] = [];
                    foreach ((array)$value as $v) {
                        $data[$fieldName][] = $v;
                    }
                    break;

                case FieldRule::A_MAP:
                    $data[$fieldName] = [];
                    foreach ((array)$value as $k => $v) {
                        $data[$fieldName][$k] = $v;
                    }
                    break;

                default:
                    break;
            }
        }

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
            // use null when not root so nested pbj message won't attempt to deserialize an empty
            // array and fail when no _schema field is present.
            $data = $isRoot ? [] : null;
        }
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @param array                $ignoredFields
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
            $child = $builder->create($fieldName, $formField->getType(), $formField->getOptions());

            // todo: verify this key is correct for data provided as option
            if (isset($options['data'][$fieldName])) {
                $child->setData($options['data'][$fieldName]);
            } else {
                $this->setFormDefault($child, $schema->getField($fieldName));
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
     * @param FormInterface|FormBuilderInterface $form
     * @param Field                              $pbjField
     */
    private function setFormDefault($form, Field $pbjField)
    {
        $pbjType = $pbjField->getType();
        $default = $pbjField->getDefault();

        if (null === $default) {
            return;
        }

        switch ($pbjField->getRule()->getValue()) {
            case FieldRule::A_SINGLE_VALUE:
                $form->setData($pbjType->encode($default, $pbjField));
                break;

            case FieldRule::A_SET:
            case FieldRule::A_LIST:
                $values = [];
                foreach ($default as $v) {
                    $values[] = $pbjType->encode($v, $pbjField);
                }
                $form->setData($values);
                break;

            case FieldRule::A_MAP:
                $values = [];
                foreach ($default as $k => $v) {
                    $values[$k] = $pbjType->encode($v, $pbjField);
                }
                $form->setData($values);
                break;

            default:
                break;
        }
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

    /**
     * Check if value is empty.
     *
     * @param array $array
     *
     * @return bool
     */
    private function isEmpty($value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        foreach ($value as $val) {
            if (is_array($val)) {
                if (!$this->isEmpty($val)) {
                    return false;
                }
            } elseif (!empty($val)) {
                return false;
            }
        }

        return true;
    }
}
