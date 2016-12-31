<?php

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\RequestHandlerTrait;
use Gdbots\Schemas\Pbjx\Request\EchoRequest;
use Gdbots\Schemas\Pbjx\Request\EchoResponse;
use Gdbots\Schemas\Pbjx\Request\EchoResponseV1;

class EchoRequestHandler implements RequestHandler
{
    use RequestHandlerTrait;

    /**
     * @param EchoRequest $request
     * @param Pbjx $pbjx
     *
     * @return EchoResponse
     */
    protected function handle(EchoRequest $request, Pbjx $pbjx)
    {
        return EchoResponseV1::create()->set('msg', $request->get('msg'));
    }
}
