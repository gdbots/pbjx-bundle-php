<?php

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Common\Enum;
use Gdbots\Pbj\Enum\FieldRule;
use Gdbots\Pbj\Enum\Format;
use Gdbots\Pbj\Enum\TypeName;
use Gdbots\Pbj\Field;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type\Type;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

final class FormFieldFactory
{
    /**
     * Map of pbj type -> symfony form type
     *
     * @var array
     */
    protected $types = [
        'big-int'           => 'todo',
        'binary'            => 'todo',
        'blob'              => 'todo',
        'boolean'           => CheckboxType::class,
        'date'              => DateType::class,
        'date-time'         => DateTimeType::class, // ensure DateUtils::ISO8601_ZULU format
        'decimal'           => NumberType::class,
        'dynamic-field'     => 'todo',
        'float'             => NumberType::class,
        'geo-point'         => 'todo',
        'identifier'        => TextType::class, //'todo',
        'int'               => IntegerType::class,
        'int-enum'          => ChoiceType::class,
        'medium-blob'       => 'todo',
        'medium-int'        => IntegerType::class,
        'medium-text'       => TextareaType::class,
        'message'           => 'todo', // this likely has to be configured manually
        'message-ref'       => TextType::class, //'todo',
        'microtime'         => 'todo',
        'signed-big-int'    => 'todo',
        'signed-int'        => IntegerType::class,
        'signed-medium-int' => IntegerType::class,
        'signed-small-int'  => IntegerType::class,
        'signed-tiny-int'   => IntegerType::class,
        'small-int'         => IntegerType::class,
        'string'            => TextType::class,
        'string-enum'       => ChoiceType::class,
        'text'              => TextareaType::class,
        'time-uuid'         => 'todo',
        'timestamp'         => TimeType::class,
        'tiny-int'          => IntegerType::class,
        'uuid'              => 'todo',
    ];

    /**
     * @param Field $field
     *
     * @return FormField
     */
    public function create(Field $field)
    {
        $symfonyType = $this->getSymfonyType($field);
        $options = $this->getOptions($field);

        if ($field->isASingleValue()) {
            if (Schema::PBJ_FIELD_NAME === $field->getName()) {
                $symfonyType = HiddenType::class;
                $options['disabled'] = true;
            }

            return new FormField($field, $symfonyType, $options);
        }

        $collectionOptions = [
            'entry_type' => $symfonyType,
            'entry_options' => $options
        ];

        return new FormField($field, CollectionType::class, $collectionOptions);
    }

    private function getSymfonyType(Field $field)
    {
        $type = $field->getType();

        if ($type->getTypeName()->equals(TypeName::STRING()) && !$field->getFormat()->equals(Format::UNKNOWN())) {
            switch ($field->getFormat()->getValue()) {
                case Format::DATE:
                    return $this->types['date'];

                case Format::DATE_TIME:
                    return $this->types['date-time'];

                case Format::EMAIL:
                    return EmailType::class;

                case Format::URL:
                    return UrlType::class;
            }
        }

        return $this->types[$type->getTypeValue()];
    }

    /**
     * @param Field $field
     *
     * @return array
     */
    private function getOptions(Field $field)
    {
        $options = [
            'required' => $field->isRequired(),
            'constraints' => [],
            'data' => $field->getDefault(),
            'mapped' => false
        ];

        //$options['empty_data'] = $options['data'];

        if ($field->isRequired()) {
            $options['constraints'][] = new NotBlank();
            $options['constraints'][] = new NotNull();
        }

        switch ($field->getType()->getTypeValue()) {
            case TypeName::STRING:
                $options['constraints'][] = new Length([
                    'min' => $field->getMinLength(),
                    'max' => $field->getMaxLength()
                ]);
                break;

            case TypeName::TEXT:
            case TypeName::MEDIUM_TEXT:
                $options['constraints'][] = new Length([
                    'min' => $field->getMinLength(),
                    'max' => $field->getMaxLength()
                ]);
                break;

            case TypeName::BOOLEAN:
                break;

            case TypeName::DATE:
                $options['format'] = 'yyyy-MM-dd';
                break;

            case TypeName::DATE_TIME:
                //$options['format'] = 'yyyy-MM-dd'; //todo: DateUtils::ISO8601_ZULU format?
                break;

            case TypeName::INT:
            case TypeName::MEDIUM_INT:
            case TypeName::SIGNED_INT:
            case TypeName::SIGNED_MEDIUM_INT:
            case TypeName::SIGNED_SMALL_INT:
            case TypeName::SIGNED_TINY_INT:
            case TypeName::SMALL_INT:
            case TypeName::TINY_INT:
                $options['constraints'][] = new Range([
                    'min' => $field->getMin(),
                    'max' => $field->getMax()
                ]);
                break;

            case TypeName::INT_ENUM:
            case TypeName::STRING_ENUM:
                /** @var Enum $className */
                $className = $field->getClassName();
                $options['choices'] = $className::values();
                break;

            default:
                break;
        }

        if (empty($options['constraints'])) {
            unset($options['constraints']);
        }

        return $options;
    }
}
