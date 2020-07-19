<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Schemas\Pbjx\Request\EchoResponseV1;

final class EchoRequestHandler implements RequestHandler
{
    public static function handlesCuries(): array
    {
        return [
            'gdbots:pbjx:request:echo-request',
        ];
    }

    public function handleRequest(Message $request, Pbjx $pbjx): Message
    {
        return EchoResponseV1::create()->set('msg', $request->get('msg'));
    }
}
