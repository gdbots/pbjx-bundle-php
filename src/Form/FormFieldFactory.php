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
        //'big-int'           => 'todo',
        //'binary'            => 'todo',
        //'blob'              => 'todo',
        'boolean'           => CheckboxType::class,
        'date'              => DateType::class,
        'date-time'         => DateTimeType::class, // ensure DateUtils::ISO8601_ZULU format
        'decimal'           => NumberType::class,
        //'dynamic-field'     => 'todo',
        'float'             => NumberType::class,
        //'geo-point'         => 'todo',
        'identifier'        => TextType::class, //'todo',
        'int'               => IntegerType::class,
        'int-enum'          => ChoiceType::class,
        //'medium-blob'       => 'todo',
        'medium-int'        => IntegerType::class,
        'medium-text'       => TextareaType::class,
        //'message'           => 'todo', // this likely has to be configured manually
        'message-ref'       => TextType::class, //'todo',
        //'microtime'         => 'todo',
        //'signed-big-int'    => 'todo',
        'signed-int'        => IntegerType::class,
        'signed-medium-int' => IntegerType::class,
        'signed-small-int'  => IntegerType::class,
        'signed-tiny-int'   => IntegerType::class,
        'small-int'         => IntegerType::class,
        'string'            => TextType::class,
        'string-enum'       => ChoiceType::class,
        'text'              => TextareaType::class,
        //'time-uuid'         => 'todo',
        'timestamp'         => TimeType::class,
        'tiny-int'          => IntegerType::class,
        //'uuid'              => 'todo',
    ];

    /**
     * @param Field $pbjField
     *
     * @return bool
     */
    public function supports(Field $pbjField)
    {
        return isset($this->types[$pbjField->getType()->getTypeValue()]);
    }

    /**
     * @param Field $pbjField
     *
     * @return FormField
     */
    public function create(Field $pbjField)
    {
        $symfonyType = $this->getSymfonyType($pbjField);
        $options = $this->getOptions($pbjField);

        if ($pbjField->isASingleValue()) {
            if (Schema::PBJ_FIELD_NAME === $pbjField->getName()) {
                $symfonyType = HiddenType::class;
            }

            return new FormField($pbjField, $symfonyType, $options);
        }

        $collectionOptions = [
            'entry_type' => $symfonyType,
            'entry_options' => $options
        ];

        return new FormField($pbjField, CollectionType::class, $collectionOptions);
    }

    /**
     * @param Field $pbjField
     *
     * @return string
     */
    private function getSymfonyType(Field $pbjField)
    {
        $pbjType = $pbjField->getType();

        if ($pbjType->getTypeName()->equals(TypeName::STRING()) && !$pbjField->getFormat()->equals(Format::UNKNOWN())) {
            switch ($pbjField->getFormat()->getValue()) {
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

        return $this->types[$pbjType->getTypeValue()];
    }

    /**
     * @param Field $pbjField
     *
     * @return array
     */
    private function getOptions(Field $pbjField)
    {
        $options = [
            'required' => $pbjField->isRequired(),
            'constraints' => [],
            'data' => $pbjField->getDefault(),
            //'mapped' => false
        ];

        //$options['empty_data'] = $options['data'];

        if ($pbjField->isRequired()) {
            $options['constraints'][] = new NotBlank();
            $options['constraints'][] = new NotNull();
        }

        switch ($pbjField->getType()->getTypeValue()) {
            case TypeName::STRING:
                $options['constraints'][] = new Length([
                    'min' => $pbjField->getMinLength(),
                    'max' => $pbjField->getMaxLength()
                ]);
                break;

            case TypeName::TEXT:
            case TypeName::MEDIUM_TEXT:
                $options['constraints'][] = new Length([
                    'min' => $pbjField->getMinLength(),
                    'max' => $pbjField->getMaxLength()
                ]);
                break;

            case TypeName::BOOLEAN:
                break;

            case TypeName::DATE:
                $options['format'] = 'yyyy-MM-dd';
                break;

            case TypeName::DATE_TIME:
                //$options['format'] = 'yyyy-MM-dd'; //todo: DateUtils::ISO8601_ZULU format?
                //$options['date_widget'] = 'single_text';
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
                    'min' => $pbjField->getMin(),
                    'max' => $pbjField->getMax()
                ]);
                break;

            case TypeName::INT_ENUM:
            case TypeName::STRING_ENUM:
                /** @var Enum $className */
                $className = $pbjField->getClassName();
                $options['data'] = (string)$pbjField->getDefault();
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
