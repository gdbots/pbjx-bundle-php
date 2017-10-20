<?php
declare(strict_types = 1);

namespace Gdbots\Tests\Bundle\PbjxBundle\Controller;

use Gdbots\Bundle\PbjxBundle\PbjxToken;
use Gdbots\Pbjx\Exception\UnexpectedValueException;
use Firebase\JWT\JWT;

class PbjxTokenTest extends \PHPUnit_Framework_TestCase
{
    private const JWT_HMAC_ALG = 'HS256';
    private const JWT_HMAC_TYP = 'JWT';
    private const JWT_DEFAULT_HOST = 'tmzdev.com';
    // String length of the base64 encoded binary signature
    //  accounting for base64 padding
    private const JWT_SIGNATURE_SIZE = [43, 44];

    public function testSignatureAlgorithmSupported()
    {
        $this->assertArrayHasKey(PbjxToken::getAlgorithm(), JWT::$supported_algs);
    }

    public function secretKeyProvider()
    {
        return [
            //len 32
            [md5((string)mt_rand(5, mt_getrandmax()))],
            //len 64
            [hash('sha256', (string)mt_rand(5, mt_getrandmax()))],
            //len 96
            [hash('sha384', (string)mt_rand(5, mt_getrandmax()))],
            //len 128
            [hash('sha512', (string)mt_rand(5, mt_getrandmax()))]
        ];
    }

    /**
     * Generates the most basic JWT token payload and returns it as an associative array.
     * @return array
     */
    private function getFakePayload()
    {
        return [
            "host" => self::JWT_DEFAULT_HOST
        ];
    }

    /**
     * Test a payload containing invalid UTF8 binary data
     *
     * @expectedException DomainException
     */
    public function testInvalidPayload()
    {
        $secret = 'af3o8ahf3a908faasdaofiahaefar3u';
        PbjxToken::create(self::JWT_DEFAULT_HOST, sha1('nope', true), $secret);
    }

    /**
     * @expectedException DomainException
     */
    public function testInvalidToken()
    {
        $secret = 'af3o8ahf3a908faasdaofiahaefar3u';
        PbjxToken::fromString('not.a.jwt', $secret);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidBinaryToken()
    {
        $secret = 'af3o8ahf3a908faasdaofiahaefar3u';
        PbjxToken::fromString(md5('not.a.jwt', true), $secret);
    }

    /**
     * @dataProvider expiredTokenProvider
     * @expectedException Firebase\JWT\ExpiredException
     */
    public function testExpiredToken($secret, $token)
    {
        $jwt = PbjxToken::fromString($token, $secret);
        $jwt->validate($secret);
    }

    public function expiredTokenProvider()
    {
        return [
            // {"host":"tmzdev.com","exp":1508467231,"content":"{\"host\":\"tmzdev.com\"}","content_signature":"MAVzkM3qu5DERObiBE2kSnB6VPgPCjoSC209fHUmIoc="}
            ["43", 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJob3N0IjoidG16ZGV2LmNvbSIsImV4cCI6MTUwODQ2NzIzMSwiY29udGVudCI6IntcImhvc3RcIjpcInRtemRldi5jb21cIn0iLCJjb250ZW50X3NpZ25hdHVyZSI6Ik1BVnprTTNxdTVERVJPYmlCRTJrU25CNlZQZ1BDam9TQzIwOWZIVW1Jb2M9In0.CS-sn2eYgOAiRNuCJ11V12MS0VmenY6d_lLMQ-1H7_c'],
            // {"host":"tmzdev.com","exp":1508467773,"content":"{\"host\":\"tmzdev.com\"}","content_signature":"MAVzkM3qu5DERObiBE2kSnB6VPgPCjoSC209fHUmIoc="}
            ["43", 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJob3N0IjoidG16ZGV2LmNvbSIsImV4cCI6MTUwODQ2Nzc3MywiY29udGVudCI6IntcImhvc3RcIjpcInRtemRldi5jb21cIn0iLCJjb250ZW50X3NpZ25hdHVyZSI6Ik1BVnprTTNxdTVERVJPYmlCRTJrU25CNlZQZ1BDam9TQzIwOWZIVW1Jb2M9In0.Wyz10AkTFOn3hHkusGv4Ih9mIPEmmG5URzsaRYjznK4']

        ];
    }

    public function staticTokenProvider()
    {
        return [
            ['af3o8ahf3a908faasdaofiahaefar3u', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImhvc3QiOiJ0bXpkZXYuY29tIiwiY29udGVudCI6ImxvIiwiY29udGVudF9zaWduYXR1cmUiOiJjS2FFUXNjUkJUNEpRTExzVjFDSmwwRGNGSzhhR3g3U3YwSkdnSDA3OHFNPSJ9.B5Wo4phTTqbmcpTSHwbPO5cehzqZjDxFAOKbk7TxviI'],
            ['af3o8ahf3a908faasdaofiahaefar3u', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJob3N0IjoidG16ZGV2LmNvbSIsImNvbnRlbnQiOiIxNTA4NDYxNDgwIiwiY29udGVudF9zaWduYXR1cmUiOiJqQm9jV29iYnlpNTQ2M01WNUV5QzBpMHNqU1ZhbEdRQzY2Vk9YVXA3QWtrPSJ9.jQahNNEP1FGynnvt-CNF7moaOPw7Ex3t8JGhMVVdNwQ'],
            ['sup33haefou8g2k', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJob3N0IjoidG16bGFicy5jb20iLCJjb250ZW50IjoiMTUwODQ2MTU2MSIsImNvbnRlbnRfc2lnbmF0dXJlIjoibVU1WlFpUlJLUU9NNEVHRDFJV3F4dGF4NXY4VkFkV1wvSDFnYUJkVmV6SG89In0.JsCJOSsyRPZWOoOMfDaWE7q8beWgQD-tVEdn8_gI69Q'],
            ['sup33haefou8g2k', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJob3N0IjoidG16bGFicy5jb20iLCJjb250ZW50IjoiMTUwODQ2MTU4OCIsImNvbnRlbnRfc2lnbmF0dXJlIjoiTGpIcG5VZ25EZTl5ZDVxOXZhOUt4K1RkaXg0bEJTNnd5NHZwYlNGZmlJND0ifQ.C0pq0hUMvaBtuaa-4TAitDrCsRjUo4y-MjPNJCALeLA']
        ];
    }

    /**
     * @dataProvider staticTokenProvider
     */
    public function testJwtSignatureMethod($secret, $token)
    {
        $jwt = PbjxToken::fromString($token, $secret);
        $this->assertEquals(mb_strlen($jwt->getSignature()), 43);
        $this->assertEquals($jwt->getPayload()->content_signature,
                            PbjxToken::getPayloadHash($jwt->getPayload()->content, $secret));
    }

    /**
     * @dataProvider secretKeyProvider
     * @expectedException Firebase\JWT\SignatureInvalidException
     */
    public function testInvalidSignatureDecode($secret)
    {
        $message = $this->getFakePayload();
        $jwt = PbjxToken::create(self::JWT_DEFAULT_HOST, $message, $secret);
        $jwt->validate('badkey');
    }

    /**
     * @dataProvider secretKeyProvider
     *
     * @param string $secret Shared secret
     */
    public function testValidSignatureCreation($secret)
    {
        $message = $this->getFakePayload();
        $jwt = PbjxToken::create(self::JWT_DEFAULT_HOST, $message, $secret);

        $headerData = $jwt->getHeader();
        $headerData = json_decode($headerData);
        $this->assertNotNull($headerData);

        $payloadData = json_decode($jwt->getPayload());
        $this->assertNotNull($payloadData);

        $this->assertEquals($headerData->alg, self::JWT_HMAC_ALG);
        $this->assertEquals($headerData->typ, self::JWT_HMAC_TYP);

        //Firebase\JWT assigns key id to 'kid' property
        //$this->assertEquals($headerData->kid, $keyid);
        $this->assertEquals($payloadData->host, $message['host']);

        $this->assertContains(strlen($jwt->getSignature()), self::JWT_SIGNATURE_SIZE);

        $this->assertNotFalse($jwt->validate($secret));
    }

    public function testJsonSerialization()
    {
        $message = $this->getFakePayload();
        $jwt = PbjxToken::create(self::JWT_DEFAULT_HOST, $message, 'secret');
        $json = json_encode($jwt);
        $jsonData = json_decode($json);
        $this->assertEquals($jsonData->signature, $jwt->getSignature());
    }
}
