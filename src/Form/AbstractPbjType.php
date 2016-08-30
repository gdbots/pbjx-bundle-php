<?php

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Pbj\Field;
use Gdbots\Pbj\Schema;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Response\ResponseV1Mixin;
use Symfony\Component\Form\AbstractType;

abstract class AbstractPbjType extends AbstractType implements PbjFormType
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
     * @param Schema $schema
     * @param array $ignoredFields
     *
     * @return FormField[]
     */
    final protected function createFormFields(Schema $schema, array $ignoredFields = [])
    {
        $ignoredFields = array_merge(array_flip($ignoredFields), self::getIgnoredFields());
        $formFields = [];
        $factory = $this->getFormFieldFactory();

        foreach ($schema->getFields() as $field) {
            $fieldName = $field->getName();
            if (isset($ignoredFields[$fieldName])) {
                continue;
            }

            if (!$factory->supports($field)) {
                continue;
            }

            $formFields[$fieldName] = $factory->create($field);
        }

        return $formFields;
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
