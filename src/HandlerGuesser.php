<?php

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbj\SchemaCurie;

/**
 * Provides a guess for what class can handle a given SchemaCurie.
 * This is used by the @see ContainerAwareServiceLocator
 */
class HandlerGuesser
{
    private $resolved = [];
    private $resolvedVendors = [];

    /**
     * Returns a fully qualified class name.
     *
     * @param SchemaCurie $curie
     *
     * @return string
     */
    final public function guessHandler(SchemaCurie $curie)
    {
        $curieStr = $curie->toString();

        if (isset($this->resolved[$curieStr])) {
            return $this->resolved[$curieStr];
        }

        $vendor = $curie->getVendor();
        if (isset($this->resolvedVendors[$vendor])) {
            $vendorStr = $this->resolvedVendors[$vendor];
        } else {
            $this->resolvedVendors[$vendor] = $vendorStr = $this->vendor($curie);
        }

        $resolved = str_replace('\\\\', '\\', sprintf(
            '%s\\%s\\%s\\%s%s',
            $vendorStr,
            $this->package($curie),
            $this->category($curie),
            $this->message($curie),
            $this->suffix($curie)
        ));

        $this->resolved[$curieStr] = $this->filterResolved($resolved);
        return $this->resolved[$curieStr];
    }

    /**
     * Final pass for "fixing" the guesser's result.
     *
     * @param string $resolved
     *
     * @return string
     */
    protected function filterResolved($resolved)
    {
        return $resolved;
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function vendor(SchemaCurie $curie)
    {
        return StringUtils::toCamelFromSlug($curie->getVendor());
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function package(SchemaCurie $curie)
    {
        $package = str_replace('.', '. ', $curie->getPackage());
        return str_replace('.', '\\', StringUtils::toCamelFromSlug($package));
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function category(SchemaCurie $curie)
    {
        return '';
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function message(SchemaCurie $curie)
    {
        return StringUtils::toCamelFromSlug($curie->getMessage());
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function suffix(SchemaCurie $curie)
    {
        return 'Handler';
    }
}
