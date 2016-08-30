<?php

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Pbj\Schema;
use Symfony\Component\Form\FormTypeInterface;

interface PbjFormType extends FormTypeInterface
{
    /**
     * Returns the underlying schema this Symfony FormType is for.
     *
     * @return Schema
     */
    public static function pbjSchema();
}
