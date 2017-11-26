<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Form;

use Gdbots\Pbj\Schema;
use Symfony\Component\Form\FormTypeInterface;

/**
 * @deprecated Our goal is to move all form functionality to the client (react/angular)
 *             and use server side validation with pbjx lifecycle events.
 */
interface PbjFormType extends FormTypeInterface
{
    /**
     * Returns the underlying schema this Symfony FormType is for.
     *
     * @return Schema
     */
    public static function pbjSchema(): Schema;
}
