<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle;

use Firebase\JWT\ExpiredException;
use Gdbots\Pbjx\Exception\UnexpectedValueException;;
use Firebase\JWT\JWT;

class PbjxToken
{
    private const DEFAULT_ALGO = 'HS256';
    private const DEFAULT_TYPE = 'JWT';
    private const DEFAULT_LEEWAY = 5; //seconds to allow time skew for time sensitive signatures
    /**
     * @var int DEFAULT_EXPIRATION Tokens will automatically expire this many seconds into the future
     */
    private const DEFAULT_EXPIRATION = 5;

    private $token;
    private $payload;
    private $signature;
    private $header;

    /**
     * Gets the currently active algorithm used for signing JWT based tokens.
     * @return string
     */
    public static function getAlgorithm()
    {
        return self::DEFAULT_ALGO;
    }

    public static function getPayloadHash($payload, $secret, $algo = 'sha256')
    {
        if(!is_string($payload)) {
            $payload = json_encode($payload);
        }
        return base64_encode(hash_hmac($algo, $payload, $secret, true));
    }

    public static function generatePayload($host, $exp = false)
    {
        $ret = [
            "host" => $host
        ];
        if ($exp !== false) {
            $ret['exp'] = time() + self::DEFAULT_EXPIRATION;
        }

        return $ret;
    }

    public static function create(string $host, $content, string $secret)
    {
        if(!is_string($content)) {
            $content = json_encode($content);
        }

        $pbjxToken = new self();
        $pbjxToken->payload = $content;

        $payload = self::generatePayload($host, true);
        $payload['content'] = $content;
        $payload['content_signature'] = self::getPayloadHash($content, $secret);

        try {
            $payloadEncoded = json_encode($payload);

            if(!$payloadEncoded) {
                throw new \DomainException('Could not encode payload');
            }

            $pbjxToken->token = JWT::encode($payload, $secret, self::DEFAULT_ALGO, null);
            $pbjxToken->parseJwtToken($pbjxToken->token);
        } catch (Exception $e) {
            throw $e;
        }

        return $pbjxToken;
    }

    public static function fromString($jwt, $secret) {
        $pbjxToken = new self();
        try {
            if($pbjxToken->parseJwtToken($jwt)) {
                $pbjxToken->payload = JWT::decode($jwt, $secret, [self::DEFAULT_ALGO]);
                return $pbjxToken;
            } else {
                throw new UnexpectedValueException('Could not parse token');
            }
        } catch (Exception $e) {
            throw $e;
        }

        return false;
    }

    public function __construct($token = false)
    {
        if ($token !== false) {
            $this->parseJwtToken($token);
        }
    }

    /**
     * Parses a JWT token as a string and assigns the header, payload and signature properties of this class.
     * No validation of signatures, claims or any other cryptographic function is done here.  If the string does
     * not contain 2 '.' characters, false will be returned.
     *
     * @param $token JWT formatted token
     * @return bool
     */
    private function parseJwtToken($token) {

        if(substr_count($token, '.') != 2) {
            return false;
        }
        $this->token = $token;
        list($header, $payload, $sig) = explode('.', $this->token);
        $this->signature = $sig;
        $this->header = base64_decode($header);
        $this->payload = base64_decode($payload);
        return true;
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function getSignature()
    {
        return $this->signature;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function sign($secret)
    {
        return JWT::sign($this->payload, $secret, self::DEFAULT_ALGO);
    }

    public function validate($secret) {
        if( $this->token) {
            try {
                $defaultLeeway = JWT::$leeway;
                JWT::$leeway = self::DEFAULT_LEEWAY;

                // If this token has a iat/nbf claim it may have been invalid before and
                // has now become possibly valid.  Otherwise an exception will be thrown.
                $decoded = JWT::decode($this->token, $secret, [self::DEFAULT_ALGO]);
                return $decoded;
            }
            catch(ExpiredException $e) {
                $this->expired = true;
                throw($e);
            }
            catch(Exception $e) {
                return false;
            }
            finally {
                JWT::$leeway = $defaultLeeway;
            }
        }

        return false;
    }





}
