<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Trait;

final class FakeCommand extends AbstractMessage implements
    CommandV1
{
    use CommandV1Trait;

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:fake-command:1-0-0', __CLASS__, [],
            [
                CommandV1Mixin::create(),
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
