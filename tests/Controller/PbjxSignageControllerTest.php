<?php
declare(strict_types = 1);

namespace Gdbots\Tests\Bundle\PbjxBundle\Controller;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Gdbots\Pbjx\Exception\UnexpectedValueException;
use Gdbots\Tests\Bundle\PbjxBundle\Fixtures\FakeCommand;
use Symfony\Component\HttpFoundation\Request;
use Firebase\JWT\JWT;

class PbjxSignageControllerTest extends \PHPUnit_Framework_TestCase
{

    const JWT_HMAC_ALG = 'HS256';
    const JWT_HMAC_TYP = 'JWT';

    private $_secret = 'af3o8ahf3a908faasdaofiahaefar3u';
    private $_header = ["alg" => self::JWT_HMAC_ALG];

    public function testSignatureAlgorithmSupported()
    {
        $this->assertArrayHasKey(self::JWT_HMAC_ALG, JWT::$supported_algs);
    }

    public function secretKeyProvider()
    {
        return [
            [mt_rand(43,43), mt_rand(10,20)],
            [mt_rand(40,40), mt_rand(10,20)],
            [mt_rand(43,43), mt_rand(10,20)],
            [mt_rand(13,13), mt_rand(10,20)]
        ];
    }

    private function getFakePayload()
    {
        $cmd = new \stdClass();
        $cmd->host = 'tmzdev.com';
        return $cmd;
    }

    public function testInvalidPayload()
    {
        $this->setExpectedException(\DomainException::class);
        JWT::encode(sha1('nope', true), $this->_secret, self::JWT_HMAC_ALG);
    }

    public function testInvalidToken()
    {
        $this->setExpectedException(\DomainException::class);
        JWT::decode(('not.a.jwt'), $this->_secret, [self::JWT_HMAC_ALG]);

        $this->setExpectedException(UnexpectedValueException::class);
        JWT::decode(md5('not.a.jwt'), $this->_secret, [self::JWT_HMAC_ALG]);
    }

    public function testExpiredToken()
    {
        $command = $this->getFakePayload();
        $command->exp = time() - 60;
        JWT::$leeway = 1;
        $jwt = JWT::encode($command, $this->_secret, self::JWT_HMAC_ALG);

        $this->setExpectedException(ExpiredException::class);
        JWT::decode($jwt, $this->_secret, [self::JWT_HMAC_ALG]);
    }

    public function testGreedyToken()
    {
        $command = $this->getFakePayload();
        $command->iat = time() + 60;
        JWT::$leeway = 1;
        $jwt = JWT::encode($command, $this->_secret, self::JWT_HMAC_ALG);

        $this->setExpectedException(BeforeValidException::class);
        JWT::decode($jwt, $this->_secret, [self::JWT_HMAC_ALG]);
    }

    public function testGreedyTokenLeeway()
    {
        $command = $this->getFakePayload();
        $command->iat = time() + 60;
        JWT::$leeway = 180;
        $jwt = JWT::encode($command, $this->_secret, self::JWT_HMAC_ALG);
        $decoded = JWT::decode($jwt, $this->_secret, [self::JWT_HMAC_ALG]);

        $this->assertEquals($decoded, $command);
    }

    public function testExpiredTokenLeeway()
    {
        $command = $this->getFakePayload();
        $command->exp = time() - 60;
        JWT::$leeway = 180;
        $jwt = JWT::encode($command, $this->_secret, self::JWT_HMAC_ALG);
        $decoded = JWT::decode($jwt, $this->_secret, [self::JWT_HMAC_ALG]);

        $this->assertEquals($decoded, $command);
    }



    public function testJwtSignatureMethod()
    {
        $sig = JWT::sign('lo', $this->_secret, self::JWT_HMAC_ALG);
        $this->assertEquals(bin2hex($sig), '70a68442c711053e0940b2ec5750899740dc14af1a1b1ed2bf4246807d3bf2a3');
    }

    public function testKnownSignature()
    {
        $jwt = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJfc2NoZW1hIjoicGJqOmdkYm90czp0ZXN0cy5wYmp4OmZpeHR1cmVzOmZha2UtY29tbWFuZDoxLTAtMCIsImNvbW1hbmRfaWQiOiJjMjRiNmY1Ni1iNDM0LTExZTctOWFmOC04MGU2NTAwNWUwNmMiLCJvY2N1cnJlZF9hdCI6IjE1MDgzNTI0MzQ5MTE1ODYiLCJjdHhfcmV0cmllcyI6MH0.wRj9yT5N64z7klPRfNQ4YyMlAa5rG_FIg4XJmhlTTGQ';

        $decoded = JWT::decode($jwt, $this->_secret, [self::JWT_HMAC_ALG]);

        $this->assertEquals($decoded->command_id,"c24b6f56-b434-11e7-9af8-80e65005e06c");
        $this->assertEquals($decoded->_schema,'pbj:gdbots:tests.pbjx:fixtures:fake-command:1-0-0');
    }

    /**
     * @dataProvider secretKeyProvider
     */
    public function testInvalidSignatureDecode($secret, $keyid)
    {
        $message = $this->getFakePayload();
        $jwt = JWT::encode($message, $secret, self::JWT_HMAC_ALG);

        $this->setExpectedException(SignatureInvalidException::class);
        JWT::decode($jwt, 'notasecret', [self::JWT_HMAC_ALG]);
    }

    /**
     * @dataProvider secretKeyProvider
     */
    public function testInvalidHmacDecode($secret, $keyid)
    {
        $message = $this->getFakePayload();
        $jwt = JWT::encode($message, $secret, self::JWT_HMAC_ALG);

        $this->setExpectedException(\UnexpectedValueException::class);
        JWT::decode($jwt, $secret, ['RS256']);
        JWT::decode($jwt, $secret, ['HS384']);
        $this->setExpectedException(null);

    }

    /**
     * @dataProvider secretKeyProvider
     */
    public function testValidSignatureCreation($secret, $keyid)
    {
        $message = $this->getFakePayload();

        $jwt = JWT::encode($message, $secret, self::JWT_HMAC_ALG, $keyid, $this->_header);
        list($header, $payload, $signature) = explode('.', $jwt);
        $headerData = base64_decode($header);
        $this->assertNotNull($headerData);
        $headerData = json_decode($headerData);
        $this->assertNotNull($headerData);

        $this->assertEquals($headerData->alg, self::JWT_HMAC_ALG);
        $this->assertEquals($headerData->typ, self::JWT_HMAC_TYP);
        //Firebase\JWT assigns key id to 'kid' property
        $this->assertEquals($headerData->kid, $keyid);

        $payloadData = base64_decode($payload);
        $this->assertNotNull($payloadData);

        $payloadData = json_decode($payloadData, false);
        $this->assertNotNull($payloadData);

        $this->assertEquals($payloadData, $message);

        $this->assertEquals(strlen($signature), 43);

        // Also does JWT::verify
        $jwtDecoded = JWT::decode($jwt, $secret, [self::JWT_HMAC_ALG]);
    }
}
