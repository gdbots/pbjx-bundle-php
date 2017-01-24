<?php
declare(strict_types = 1);

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
     * @param Field  $pbjField
     * @param string $type
     * @param array  $options
     */
    public function __construct(Field $pbjField, string $type, array $options = [])
    {
        $this->pbjField = $pbjField;
        $this->type = $type;
        $this->options = $options;
    }

    /**
     * @return Field
     */
    public function getPbjField(): Field
    {
        return $this->pbjField;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->pbjField->getName();
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return self
     */
    public function setOption(string $name, $value): self
    {
        $this->options[$name] = $value;
        return $this;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function removeOption(string $name): self
    {
        unset($this->options[$name]);
        return $this;
    }
}
