<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle;

use Firebase\JWT\ExpiredException;
use Gdbots\Pbjx\Exception\UnexpectedValueException;;
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
    private $_expired = false;

    public function __construct()
    {
        JWT::$leeway = self::DEFAULT_LEEWAY;
    }

    public function setLeeway($seconds) {
        if(!is_numeric($seconds)) {
            return false;
        }
        JWT::$leeway = (int)$seconds;
        return true;
    }

    public function getLeeway($seconds) {
        return JWT::$leeway;
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
                        $this->_expired = true;
                        return true;
                    }
                }

                //iat date check
                if (isset($this->_payload->iat)) {
                    if ($this->_payload->iat > ($timestamp + JWT::$leeway)) {
                        $this->_expired = true;
                        return true;
                    }
                }
            }
        }
        $this->_expired = false;
        return false;
    }

    private function parseJwtToken($token) {
        if(substr_count($token, '.') != 2) {
            return false;
        }
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

    public function sign($secret, $algo = self::DEFAULT_ALGO) {
        return JWT::sign($this->_payload, $secret, $algo);
    }

    public function validate($secret, $algo = self::DEFAULT_ALGO) {
        if( $this->_token) {
            try {
                $decoded = JWT::decode($this->_token, $secret, [$algo]);
                return $decoded;
            }
            catch(ExpiredException $e) {
                $this->_expired = true;
                throw($e);
            }
            catch(Exception $e) {
                return false;
            }
        }

        return false;
    }

    public static function fromString($jwt, $secret, $algo = self::DEFAULT_ALGO) {
        $pbjxSignature = new self();
        try {
            if($pbjxSignature->parseJwtToken($jwt)) {
                $pbjxSignature->_payload = JWT::decode($jwt, $secret, [$algo]);
                $pbjxSignature->_signed = true;
                $pbjxSignature->_valid = true;
                return $pbjxSignature;
            } else {
                throw new UnexpectedValueException('Could not parse token');
            }
        } catch (Exception $e) {
            throw $e;
        }

        return false;
    }

    public static function getPayloadHash($payload, $secret, $algo = 'sha256')
    {
        return base64_encode(hash_hmac($algo, $payload, (string)$secret, true));
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

                $payloadHash = self::getPayloadHash($payloadEncoded, $secret);
                $pbjxSignature->_token = JWT::encode($payload, $secret, $algo, null, [
                    'payload_hash' => $payloadHash
                ]);
                $pbjxSignature->parseJwtToken($pbjxSignature->_token);
            } catch (Exception $e) {
                throw $e;
            }
        }

        return $pbjxSignature;
    }

}
