<?php
declare(strict_types=1);

namespace Gdbots\Tests\Bundle\PbjxBundle;

use Gdbots\Bundle\PbjxBundle\PbjxTokenSigner;
use Gdbots\Pbjx\PbjxToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class PbjxTokenSignerTest extends TestCase
{
    public function testSign()
    {
        $content = 'content';
        $aud = 'http://localhost/pbjx';
        $kid = 'kid';
        $secret = 'secret';

        $signer = new PbjxTokenSigner([['kid' => $kid, 'secret' => $secret]]);

        $token = $signer->sign($content, $aud);
        $signer->validate($content, $aud, $token->toString());
        $this->assertTrue(true);
    }

    public function testValidate()
    {
        $content = 'content';
        $aud = 'http://localhost/pbjx';
        $kid = 'kid';
        $secret = 'secret';

        $signer = new PbjxTokenSigner([['kid' => $kid, 'secret' => $secret]]);

        $token = PbjxToken::create($content, $aud, $kid, $secret);

        $signer->validate($content, $aud, $token->toString());
        $this->assertTrue(true);

        try {
            $signer->validate('invalid-content', $aud, $token->toString());
            $validated = true;
        } catch (\Throwable $e) {
            $validated = false;
        }
        $this->assertFalse($validated, 'Invalid content validated');

        try {
            $signer->validate($content, 'invalid-aud', $token->toString());
            $validated = true;
        } catch (\Throwable $e) {
            $validated = false;
        }
        $this->assertFalse($validated, 'Invalid aud validated');

        try {
            $signer->validate($content, $aud, 'invalid-token');
            $validated = true;
        } catch (\Throwable $e) {
            $validated = false;
        }
        $this->assertFalse($validated, 'Invalid token validated');

        $signer = new PbjxTokenSigner([], 'kid');
        try {
            $signer->validate($content, $aud, $token->toString());
            $validated = true;
        } catch (\Throwable $e) {
            $validated = false;
        }
        $this->assertFalse($validated, 'signer with no keys validated');
    }

    public function testValidateWithBearer()
    {
        $content = 'content';
        $aud = 'http://localhost/pbjx';
        $kid = 'bearer';
        $secret = 'ajwtvalueisexpectedhere';

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(
            new PreAuthenticatedToken('user', "Bearer $secret", 'test')
        );

        $signer = new PbjxTokenSigner([], null, $tokenStorage);
        $token = PbjxToken::create($content, $aud, $kid, $secret);

        $signer->validate($content, $aud, $token->toString());
        $this->assertTrue(true);
    }
}
