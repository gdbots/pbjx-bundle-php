<?php

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Pbj\Field;

class FormField
{
    /** @var Field */
    private $pbjField;

    /** @var string */
    private $type;

    /** @var array */
    private $options;

    /**
     * @param Field $pbjField
     * @param string $type
     * @param array $options
     */
    public function __construct(Field $pbjField, $type, array $options = [])
    {
        $this->pbjField = $pbjField;
        $this->type = $type;
        $this->options = $options;
    }

    /**
     * @return Field
     */
    public function getPbjField()
    {
        return $this->pbjField;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->pbjField->getName();
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
