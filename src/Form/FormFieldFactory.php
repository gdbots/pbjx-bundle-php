<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Common\Enum;
use Gdbots\Pbj\Enum\Format;
use Gdbots\Pbj\Enum\TypeName;
use Gdbots\Pbj\Field;
use Gdbots\Pbj\Schema;
use Gdbots\Bundle\PbjxBundle\Form\Type\DatePickerType;
use Gdbots\Bundle\PbjxBundle\Form\Type\DateTimePickerType;
use Gdbots\Bundle\PbjxBundle\Form\Type\CollectionType;
use Gdbots\Bundle\PbjxBundle\Form\Type\DynamicFieldType;
use Gdbots\Bundle\PbjxBundle\Form\Type\GeoPointType;
use Gdbots\Bundle\PbjxBundle\Form\Type\KeyValueType;
use Gdbots\Bundle\PbjxBundle\Form\Type\SwitcheryType;
use Gdbots\Bundle\PbjxBundle\Form\Type\TrinaryType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
use Symfony\Component\Validator\Constraints\Regex;

final class FormFieldFactory
{
    /**
     * Map of pbj type -> symfony form type.
     *
     * @var array
     */
    protected $types = [
        'big-int'           => TextType::class,
        //'binary'            => 'todo', // todo: handle as file or textarea?
        //'blob'              => 'todo', // todo: ref binary
        'boolean'           => SwitcheryType::class,
        'date'              => DatePickerType::class,
        'date-time'         => DateTimePickerType::class, // ensure DateUtils::ISO8601_ZULU format
        'decimal'           => NumberType::class,
        'dynamic-field'     => DynamicFieldType::class,
        'float'             => NumberType::class,
        'geo-point'         => GeoPointType::class,
        'identifier'        => TextType::class,
        'int'               => IntegerType::class, //set divisor (for all ints)
        'int-enum'          => ChoiceType::class,
        //'medium-blob'       => 'todo',
        'medium-int'        => IntegerType::class,
        'medium-text'       => TextareaType::class,
        //'message'           => null, // message type require its own form type
        'message-ref'       => TextType::class, //'todo',
        'microtime'         => TextType::class,
        'signed-big-int'    => TextType::class,
        'signed-int'        => IntegerType::class,
        'signed-medium-int' => IntegerType::class,
        'signed-small-int'  => IntegerType::class,
        'signed-tiny-int'   => IntegerType::class,
        'small-int'         => IntegerType::class,
        'string'            => TextType::class, // handle patterns (as html5 validations) and known formats
        'string-enum'       => ChoiceType::class,
        'text'              => TextareaType::class, // todo: set max
        'time-uuid'         => TextType::class,
        'timestamp'         => TimeType::class,
        'tiny-int'          => IntegerType::class,
        'trinary'           => TrinaryType::class,
        'uuid'              => TextType::class
    ];

    /**
     * @param Field $pbjField
     *
     * @return bool
     */
    public function supports(Field $pbjField): bool
    {
        return isset($this->types[$pbjField->getType()->getTypeValue()]);
    }

    /**
     * @param Field $pbjField
     *
     * @return FormField
     */
    public function create(Field $pbjField): FormField
    {
        $symfonyType = $this->getSymfonyType($pbjField);
        $options = $this->getOptions($pbjField);

        if ($pbjField->isASingleValue()) {
            if (Schema::PBJ_FIELD_NAME === $pbjField->getName()) {
                $symfonyType = HiddenType::class;
                $options['data'] = $pbjField->getDefault();
            }

            return new FormField($pbjField, $symfonyType, $options);
        }

        // handle maps (assoc array)
        if ($pbjField->isAMap()) {
            $options = array_merge([
                'required' => $options['required'],
                'value_type' => $symfonyType,
                'value_options' => $options
            ]);

            $symfonyType = KeyValueType::class;
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
    private function getSymfonyType(Field $pbjField): string
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
    private function getOptions(Field $pbjField): array
    {
        $options = [
            'required' => $pbjField->isRequired(),
            'constraints' => []
        ];

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

                if ($pattern = $pbjField->getPattern()) {
                    $options['constraints'][] = new Regex([
                        'pattern' => '/'.trim($pattern, '/').'/'
                    ]);
                }

                // fixme: handle any "Format" options not handled in getSymfonyType, i.e. hashtag
                break;

            case TypeName::UUID:
            case TypeName::TIME_UUID:
                $options['constraints'][] = new Regex([
                    'pattern' => '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/'
                ]);
                break;

            case TypeName::IDENTIFIER:
                $options['constraints'][] = new Regex([
                    'pattern' => '/^[\\w\\.-_]+$/'
                ]);
                $options['constraints'][] = new Length([
                    'min' => $pbjField->getMinLength(),
                    'max' => $pbjField->getMaxLength()
                ]);
                break;

            case TypeName::MICROTIME:
                $options['constraints'][] = new Regex([
                    'pattern' => '/^[1-9]{1}[0-9]{12,15}$/'
                ]);
                break;

            case TypeName::BIG_INT:
            case TypeName::SIGNED_BIG_INT:
                $options['constraints'][] = new Regex([
                    'pattern' => '/^\-?\d+$/'
                ]);
                break;

            case TypeName::TEXT:
            case TypeName::MEDIUM_TEXT:
                $options['constraints'][] = new Length([
                    'min' => $pbjField->getMinLength(),
                    'max' => $pbjField->getMaxLength()
                ]);
                break;

            case TypeName::DATE:
                $options['format'] = 'yyyy-MM-dd';
                break;

            case TypeName::DATE_TIME:
                $options['widget'] = 'single_text';
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
                $options['choices'] = array_filter($className::values(), function ($v) {
                    return 'unknown' !== $v;
                });
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
