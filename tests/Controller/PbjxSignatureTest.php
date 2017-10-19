<?php
declare(strict_types = 1);

namespace Gdbots\Tests\Bundle\PbjxBundle\Controller;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Gdbots\Pbjx\Exception\UnexpectedValueException;
use Firebase\JWT\JWT;

use Gdbots\Bundle\PbjxBundle\PbjxSignature;

class PbjxSignatureTest extends \PHPUnit_Framework_TestCase
{

    const JWT_HMAC_ALG = 'HS256';
    const JWT_HMAC_TYP = 'JWT';
    // String length of the base64 encoded binary signature
    //  accounting for base64 padding
    const JWT_SIGNATURE_SIZE = [43, 44];

    private $_secret = 'af3o8ahf3a908faasdaofiahaefar3u';

    public function testSignatureAlgorithmSupported()
    {
        $this->assertArrayHasKey(PbjxSignature::DEFAULT_ALGO, JWT::$supported_algs);
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

    public function testSignatureBypass()
    {
        /*
        $command = $this->getFakePayload();
        $jwt = JWT::encode($command, $this->_secret, self::JWT_HMAC_ALG);
        $parts = explode('.', $jwt);
        //TODO: set alg to a serialized object that string casts to the wrong name
        $parts[0] = base64_encode('{"typ":"JWT","alg":"none"}');
        $parts = implode('.', $parts);

        $decoded = JWT::decode($parts, "\x0\x0", ['none']);
        */
    }

    public function testInvalidPayload()
    {
        $this->setExpectedException(\DomainException::class);
        $pbjxSignature = PbjxSignature::create(sha1('nope', true), $this->_secret);
    }

    public function testInvalidToken()
    {
        $this->setExpectedException(\DomainException::class);
        PbjxSignature::fromString('not.a.jwt', $this->_secret);

        $this->setExpectedException(UnexpectedValueException::class);
        PbjxSignature::fromString(md5('not.a.jwt', true), $this->_secret);
    }

    public function testExpiredToken()
    {
        $this->setExpectedException(ExpiredException::class);

        $command = $this->getFakePayload();
        $command->exp = time() - 60;

        $jwt = PbjxSignature::create($command, $this->_secret);
        $jwt->setLeeway(1);
        $jwt->validate($this->_secret);
    }

    public function testGreedyToken()
    {
        $this->setExpectedException(BeforeValidException::class);

        $command = $this->getFakePayload();
        $command->iat = time() + 60;
        $jwt = PbjxSignature::create($command, $this->_secret);
        $jwt->setLeeway(1);
        $jwt->validate($this->_secret);
    }

    public function testGreedyTokenLeeway()
    {
        $command = $this->getFakePayload();
        $command->iat = time() + 60;
        $jwt = PbjxSignature::create($command, $this->_secret);
        $jwt->setLeeway(180);
        $decoded = $jwt->validate($this->_secret);

        $this->assertEquals($decoded, $command);
    }

    public function testExpiredTokenLeeway()
    {
        $command = $this->getFakePayload();
        $command->exp = time() - 60;
        $jwt = PbjxSignature::create($command, $this->_secret);
        $jwt->setLeeway(180);
        $decoded = $jwt->validate($this->_secret);

        $this->assertEquals($decoded, $command);
    }

    public function testJwtSignatureMethod()
    {
        $message = 'lo';
        $jwt = PbjxSignature::create($message, $this->_secret);
        $sig = $jwt->sign($this->_secret);
        $this->assertEquals(bin2hex($sig), '70a68442c711053e0940b2ec5750899740dc14af1a1b1ed2bf4246807d3bf2a3');
    }

    public function testKnownSignature()
    {
        $jwtString = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJfc2NoZW1hIjoicGJqOmdkYm90czp0ZXN0cy5wYmp4OmZpeHR1cmVzOmZha2UtY29tbWFuZDoxLTAtMCIsImNvbW1hbmRfaWQiOiJjMjRiNmY1Ni1iNDM0LTExZTctOWFmOC04MGU2NTAwNWUwNmMiLCJvY2N1cnJlZF9hdCI6IjE1MDgzNTI0MzQ5MTE1ODYiLCJjdHhfcmV0cmllcyI6MH0.wRj9yT5N64z7klPRfNQ4YyMlAa5rG_FIg4XJmhlTTGQ';
        $jwt = PbjxSignature::fromString($jwtString, $this->_secret);
        $decoded = $jwt->getPayload();

        $this->assertEquals($decoded->command_id,"c24b6f56-b434-11e7-9af8-80e65005e06c");
        $this->assertEquals($decoded->_schema,'pbj:gdbots:tests.pbjx:fixtures:fake-command:1-0-0');
    }

    /**
     * @dataProvider secretKeyProvider
     */
    public function testInvalidSignatureDecode($secret, $keyid)
    {
        $this->setExpectedException(SignatureInvalidException::class);
        $message = $this->getFakePayload();
        $jwt = PbjxSignature::create($message, $this->_secret);
        $jwt->validate('badkey');
    }

    /**
     * @dataProvider secretKeyProvider
     */
    public function testInvalidHmacDecode($secret, $keyid)
    {
        $message = $this->getFakePayload();
        $jwt = PbjxSignature::create($message, (string)$secret);

        $this->setExpectedException(\UnexpectedValueException::class);
        $jwt->validate((string)$secret, 'RS256');
        $jwt->validate((string)$secret, 'HS384');
    }

    /**
     * @dataProvider secretKeyProvider
     */
    public function testValidSignatureCreation($secret, $keyid)
    {
        $message = $this->getFakePayload();

       // $jwt = JWT::encode($message, $secret, self::JWT_HMAC_ALG, $keyid, $this->_header);
        $jwt = PbjxSignature::create($message, (string)$secret);

        //TODO: convenience methods
        list($header, $payload, $signature) = explode('.', $jwt->getToken());
        $headerData = base64_decode($header);
        $this->assertNotNull($headerData);
        $headerData = json_decode($headerData);
        $this->assertNotNull($headerData);
        $payloadData = base64_decode($payload);
        $this->assertNotNull($payloadData);


        $this->assertEquals($headerData->alg, self::JWT_HMAC_ALG);
        $this->assertEquals($headerData->typ, self::JWT_HMAC_TYP);
        $this->assertEquals($headerData->payload_hash, PbjxSignature::getPayloadHash($payloadData, (string)$secret));
        //Firebase\JWT assigns key id to 'kid' property
        //$this->assertEquals($headerData->kid, $keyid);
        $payloadData = json_decode($payloadData, false);
        $this->assertNotNull($payloadData);
        $this->assertEquals($payloadData, $message);

        $this->assertContains(strlen($signature), self::JWT_SIGNATURE_SIZE);

        $this->assertNotFalse($jwt->validate($secret));

    }
}
