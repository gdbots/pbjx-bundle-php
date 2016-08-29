<?php

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Pbj\Schema;
use Symfony\Component\Form\FormTypeInterface;

interface PbjFormType extends FormTypeInterface
{
    /**
     * @return Schema
     */
    public static function schema();
}
