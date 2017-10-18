<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Gdbots\Pbjx\Exception\UnexpectedValueException;
use Gdbots\Tests\Bundle\PbjxBundle\Fixtures\FakeCommand;
use Symfony\Component\HttpFoundation\Request;
use Firebase\JWT\JWT;

class PbjxSignature
{
    const DEFAULT_ALGO = 'HS256';
    const DEFAULT_TYPE = 'JWT';
    const DEFAULT_LEEWAY = 10; //seconds to allow time skew for time sensitive signatures

    private $_token;
    private $_payload;
    private $_valid = false;
    private $_signed = false;
    private $_signature;
    private $_header;

    public function __construct()
    {
        JWT::$leeway = self::DEFAULT_LEEWAY;
    }

    public function isValid() {
        return $this->_valid;
    }

    public function isExpired() {
        if ($this->_valid) {
            if ($this->_payload) {
                $timestamp = time();
                //expiration date check
                if (isset($this->_payload->exp)) {
                    if (($timestamp - JWT::$leeway) >= $this->_payload->exp ) {
                        return true;
                    }
                }

                //iat date check
                if (isset($this->_payload->iat)) {
                    if ($this->_payload->iat > ($timestamp + JWT::$leeway)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function parseJwtToken($token) {
        $this->_token = $token;
        if ($this->_token) {
            list($header, $payload, $sig) = explode('.', $this->_token);
            $this->_signature = $sig;
            $this->_header = $header;
            return true;
        }

        return false;
    }

    public function getSignature() {
        if ($this->_signature) {
            return $this->_signature;
        }
    }

    public function getPayload() {
        if ($this->_payload) {
            return $this->_payload;
        }
    }

    public function getToken() {
        if ($this->_token) {
            return $this->_token;
        }
    }

    public static function fromString($jwt, $secret, $algo = self::DEFAULT_ALGO) {
        $pbjxSignature = new self();
        try {
            $pbjxSignature->parseJwtToken($jwt);
            $pbjxSignature->_payload = JWT::decode($jwt, $secret, [$algo]);
            $pbjxSignature->_signed = true;
            $pbjxSignature->_valid = true;
            return $pbjxSignature;
        } catch (Exception $e) {
            throw $e;
        }

        return false;
    }

    public static function create($payload, $secret = false, $algo = self::DEFAULT_ALGO)
    {
        $pbjxSignature = new self();
        $pbjxSignature->_payload = $payload;
        $pbjxSignature->_valid = false;
        $pbjxSignature->_signed = false;

        if ($secret) {
            try {
                $payloadEncoded = json_encode($payload);

                if(!$payloadEncoded) {
                    throw new \DomainException('Could not encode payload');
                }

                $token = JWT::encode($payload, $secret, $algo, null, [
                    'payload_hash' => base64_encode(hash_hmac('sha256', $payloadEncoded, $secret, true))
                ]);
                $pbjxSignature->parseJwtToken($token);
            } catch (Exception $e) {
                throw $e;
            }
        }

        return $pbjxSignature;
    }

}
