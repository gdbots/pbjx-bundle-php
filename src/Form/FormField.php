<?php

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Pbj\Field;

class FormField
{
    /** @var Field */
    private $field;

    /** @var string */
    private $type;

    /** @var array */
    private $options;

    /**
     * @param Field $field
     * @param string $type
     * @param array $options
     */
    public function __construct(Field $field, $type, array $options = [])
    {
        $this->field = $field;
        $this->type = $type;
        $this->options = $options;
    }

    /**
     * @return Field
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->field->getName();
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
}
