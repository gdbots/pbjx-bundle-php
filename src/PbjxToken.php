<?php
declare(strict_types = 1);

namespace Gdbots\Bundle\PbjxBundle;

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Gdbots\Pbjx\Exception\UnexpectedValueException;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Represents a signed PBJX-JWT token.  PBJX-JWT is a type of JWS.
 *
 * Class PbjxToken
 * @package Gdbots\Bundle\PbjxBundle
 * @see http://self-issued.info/docs/draft-ietf-jose-json-web-signature.html JWS draft specification
 */
class PbjxToken implements \JsonSerializable
{
    /**
     * @var string DEFAULT_ALGO The default algorithm and type of encryption scheme to use when signing JWT tokens.  Currently only HS256 (HMAC-SHA-256) is supported or allowed.
     */
    private const DEFAULT_ALGO = 'HS256';
    /**
     * @var int DEFAULT_LEEWAY Seconds to allow time skew for time sensitive signatures
     */
    private const DEFAULT_LEEWAY = 5;
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
    public static function getAlgorithm(): string
    {
        return self::DEFAULT_ALGO;
    }

    /**
     * @param string $payload The content to hash
     * @param string $secret Shared secret
     * @return string A base64-encoded hmac
     */
    public static function getPayloadHash(string $payload, string $secret) : string
    {
        return JWT::urlsafeB64Encode(hash_hmac('sha256', $payload, $secret, true));
    }

    /**
     * @param string $host The host or endpoint that this payload is being sent to
     * @param mixed $content The content to include in the payload.
     * @return array The default structure for all PBJX tokens
     */
    public static function generatePayload(string $host, string $content, string $secret): array
    {
        $ret = [
            "host" => $host,
            "pbjx" => self::getPayloadHash($content, $secret),
            "exp"  => time() + self::DEFAULT_EXPIRATION
        ];

        return $ret;
    }

    /**
     * @param string $host Pbjx host or service name
     * @param $content Pbjx content
     * @param string $secret Shared secret
     * @return PbjxToken
     * @throws \Exception If the token could not be created
     * @throws DomainException If the content cannot be json encoded using json_encode
     */
    public static function create(string $host, string $content, string $kId, string $secret) : PbjxToken
    {
        if (!kId || empty($kId)) {
            throw \Gdbots\Pbjx\Exception\InvalidArgumentException('$kId Value is a required parameter');
        }

        if (!$content || empty($content)) {
            throw \Gdbots\Pbjx\Exception\InvalidArgumentException('$content Value is a required parameter');
        }

        $pbjxToken = new self();
        $pbjxToken->payload = $content;
        $payload = self::generatePayload($host, $content, $secret);

        try {
            $payloadEncoded = json_encode($payload);

            if(!$payloadEncoded) {
                throw new \DomainException('Could not encode payload');
            }

            $pbjxToken->token = JWT::encode($payload, $secret, self::DEFAULT_ALGO, $kId);
            $pbjxToken->parseJwtToken($pbjxToken->token);
        } catch (Exception $e) {
            throw $e;
        }

        return $pbjxToken;
    }

    /**
     * Parse a JWT token and attempt to decode it using the user supplied secret
     *
     * @param string $jwt A JWT formatted token
     * @param string $secret Shared secret
     * @return PbjxToken
     * @throws \Exception If the token could not be decoded
     * @throws UnexpectedValueException If the token could not be parsed
     */
    public static function fromString(string $jwt, string $secret): PbjxToken
    {
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
    }

    /**
     * PbjxToken constructor.
     * @param string $token Optional JWT token string to parse on construction of this object.
     * @return PbjxToken
     */
    public function __construct(string $token = null)
    {
        if (!empty($token)) {
            $this->parseJwtToken($token);
        }
    }

    /**
     * Parses a JWT token as a string and assigns the header, payload and signature properties of this class.
     * No validation of signatures, claims or any other cryptographic function is done here.  If the string does
     * not contain 2 '.' characters, false will be returned.
     *
     * @param string $token JWT formatted token
     * @return bool
     */
    private function parseJwtToken(string $token): bool
    {

        if(substr_count($token, '.') != 2) {
            return false;
        }
        $this->token = $token;
        list($header, $payload, $sig) = explode('.', $this->token);
        $this->signature = $sig;
        $this->header = JWT::urlsafeB64Decode($header);
        $this->payload = JWT::urlsafeB64Decode($payload);
        return true;
    }

    /**
     * @return string The decoded header
     */
    public function getHeader(): string
    {
        return $this->header;
    }

    /**
     * @return string The signature in base64 format
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * @return string The decoded payload
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return string The full JWT formatted token string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Create just the signature portion for a JWT payload
     *
     * @param string $secret Shared secret
     * @return string
     * @throws DomainException Unsupported algorithm was specified
     */
    public function sign($secret): string
    {
        return JWT::sign($this->payload, $secret, self::DEFAULT_ALGO);
    }

    /**
     * Attempts to decode the current jwt token using the supplied secret.
     *
     * @param string $secret Shared secret
     * @return bool False is the token is invalid, otherwise True
     * @throws ExpiredException If the token has expired
     * @throws Exception The token was malformed or could not be decoded
     */
    public function validate(string $secret): bool
    {
        if($this->token) {
            try {
                //since JWT::$leeway is static, we only set it when needed then reset it to the default (0)
                $defaultLeeway = JWT::$leeway;
                JWT::$leeway = self::DEFAULT_LEEWAY;

                // If this token has a iat/nbf claim it may have been invalid before and
                // has now become possibly valid.  Otherwise an exception will be thrown.
                $decoded = JWT::decode($this->token, $secret, [self::DEFAULT_ALGO]);

                if (!isset($decoded->exp) || empty($decoded->exp)) {
                    throw new Exception("Expiration date was not found on this token");
                }

                return true;
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

    /**
     * Returns a string representation of an encoded JWT Token
     * @return string
     */
    public function __toString(): string
    {
        return $this->getToken();
    }

    /**
     * Returns a json encoded representation of a decoded JWT Token
     * @return string
     */
    public function toJson() : string
    {
        return json_encode($this);
    }

    public function jsonSerialize() : array
    {
        return [
            'header' => $this->getHeader(),
            'payload' => $this->getPayload(),
            'signature' => $this->getSignature()
        ];
    }
}
