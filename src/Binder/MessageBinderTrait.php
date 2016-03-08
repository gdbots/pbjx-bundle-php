<?php

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbj\Field;
use Gdbots\Pbj\Message;
use Symfony\Component\HttpFoundation\RequestStack;

trait MessageBinderTrait
{
    /** @var RequestStack */
    protected $requestStack;

    /**
     * @param Message $message
     * @param Field[] $fields
     * @param array $input
     */
    protected function restrictBindFromInput(Message $message, array $fields, array $input)
    {
        foreach ($fields as $field) {
            $fieldName = $field->getName();

            if (!$message->has($fieldName)) {
                // this means whatever was in the input never made it to the message.
                continue;
            }

            if (!isset($input[$fieldName])) {
                // the field in question doesn't exist in the input used to populate the message.
                // so whatever the value is was either a default or set by another process.
                continue;
            }

            // the input was used to populate the field on the message but they weren't allowed
            // to provide that field, only the server can set it.
            $message->clear($fieldName);
        }
    }
}
