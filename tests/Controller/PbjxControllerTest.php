<?php
declare(strict_types=1);

namespace Gdbots\Tests\Bundle\PbjxBundle\Controller;

use Gdbots\Bundle\PbjxBundle\Controller\PbjxController;
use Gdbots\Bundle\PbjxBundle\PbjxTokenSigner;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Tests\Bundle\PbjxBundle\Fixtures\FakeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

// todo: setup test kernel so we can use WebTestCase
//use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PbjxControllerTest extends TestCase
{
    public function test()
    {
        $pbjx = (new RegisteringServiceLocator())->getPbjx();
        $signer = new PbjxTokenSigner([['kid' => 'kid', 'secret' => 'secret']]);
        $controller = new PbjxController($pbjx, $signer);

        $command = FakeCommand::create();
        $schema = $command::schema();
        $curie = $schema->getCurie();
        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('Content-Type', 'application/json');
        $request->setRequestFormat('json');

        $request->attributes->set('pbjx_vendor', $curie->getVendor());
        $request->attributes->set('pbjx_package', $curie->getPackage());
        $request->attributes->set('pbjx_category', $curie->getCategory());
        $request->attributes->set('pbjx_message', $curie->getMessage());
        $request->attributes->set('pbjx_bind_unrestricted', true);
        $request->attributes->set('pbjx_console', true);

        $envelope = $controller->handleAction($request);

        $this->assertInstanceOf('Gdbots\Schemas\Pbjx\Envelope', $envelope);
    }
}
