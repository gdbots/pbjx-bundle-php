<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbj\SchemaCurie;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    final public function guessHandler(SchemaCurie $curie): string
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
     * Creates a handler for the provided SchemaCurie.  The $className originates
     * from the guessHandler method and should exist so doing new $className should work.
     *
     * @param SchemaCurie        $curie
     * @param string             $className
     * @param ContainerInterface $container
     *
     * @return mixed
     */
    public function createHandler(SchemaCurie $curie, string $className, ContainerInterface $container)
    {
        $handler = new $className;

        if ($handler instanceof LoggerAwareInterface) {
            $handler->setLogger($container->get('logger'));
        }

        return $handler;
    }

    /**
     * Final pass for "fixing" the guesser's result.
     *
     * @param string $resolved
     *
     * @return string
     */
    protected function filterResolved(string $resolved): string
    {
        return $resolved;
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function vendor(SchemaCurie $curie): string
    {
        return StringUtils::toCamelFromSlug($curie->getVendor());
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function package(SchemaCurie $curie): string
    {
        $package = str_replace('.', '. ', $curie->getPackage());

        return str_replace('.', '\\', StringUtils::toCamelFromSlug($package));
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function category(SchemaCurie $curie): string
    {
        return '';
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function message(SchemaCurie $curie): string
    {
        return StringUtils::toCamelFromSlug($curie->getMessage());
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return string
     */
    protected function suffix(SchemaCurie $curie): string
    {
        return 'Handler';
    }
}
