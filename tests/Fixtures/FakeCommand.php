<?php
declare(strict_types=1);

namespace Gdbots\Tests\Bundle\PbjxBundle\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Trait;

final class FakeCommand extends AbstractMessage
{
    const COMMAND_ID_FIELD = 'command_id';

    use CommandV1Trait;

    protected static function defineSchema(): Schema
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:fake-command:1-0-0', __CLASS__,
            CommandV1Mixin::getFields(),
            [
                CommandV1Mixin::SCHEMA_CURIE_MAJOR,
                CommandV1Mixin::SCHEMA_CURIE,
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
