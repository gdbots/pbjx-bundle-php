<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\RequestHandlerTrait;
use Gdbots\Schemas\Pbjx\Request\EchoRequest;
use Gdbots\Schemas\Pbjx\Request\EchoRequestV1;
use Gdbots\Schemas\Pbjx\Request\EchoResponse;
use Gdbots\Schemas\Pbjx\Request\EchoResponseV1;

final class EchoRequestHandler implements RequestHandler
{
    use RequestHandlerTrait;

    /**
     * @param EchoRequest $request
     * @param Pbjx        $pbjx
     *
     * @return EchoResponse
     */
    protected function handle(EchoRequest $request, Pbjx $pbjx): EchoResponse
    {
        return EchoResponseV1::create()->set('msg', $request->get('msg'));
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            EchoRequestV1::schema()->getCurie(),
        ];
    }
}
