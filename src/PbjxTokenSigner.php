<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbjx\PbjxToken;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PbjxTokenSigner
{
    /**
     * Default kid to use when creating new tokens.
     *
     * @var string
     */
    private $defaultKid;

    /**
     * An array of secrets keyed by the kid used to
     * create and validate signatures.
     *
     * @var string[]
     */
    private $keys = [];

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /**
     * @param array                 $keys
     * @param string                $defaultKid
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(array $keys, ?string $defaultKid = null, ?TokenStorageInterface $tokenStorage = null)
    {
        $backupKid = null;
        foreach ($keys as $key) {
            $this->keys[$key['kid']] = $key['secret'];
            $backupKid = $key['kid'];
        }

        $this->defaultKid = $defaultKid ?: $backupKid;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Creates a new signed token for the provided content.
     *
     * @param string $content Pbjx content that is been signed
     * @param string $aud     Pbjx endpoint this token will be sent to
     * @param string $kid     Key ID used to sign the token.
     *
     * @return PbjxToken
     */
    public function sign(string $content, string $aud, ?string $kid = null): PbjxToken
    {
        $kid = $kid ?: $this->defaultKid;
        return PbjxToken::create($content, $aud, $kid, $this->getSecret($kid));
    }

    /**
     * Validates that the provided token is valid and okay to use
     * and also creates a new token with the same secret and content
     * and compares our result to the provided token to determine
     * if they are an exact match.
     *
     * If no exception is thrown the token is valid.
     *
     * @param string $content Pbjx content that has been signed
     * @param string $aud     Pbjx endpoint this token was sent to
     * @param string $token   The token string (typically from header x-pbjx-token)
     *
     * @throws \Throwable
     */
    public function validate(string $content, string $aud, string $token): void
    {
        $actualToken = PbjxToken::fromString($token);
        $expectedToken = PbjxToken::create(
            $content,
            $aud,
            $actualToken->getKid(),
            $this->getSecret($actualToken->getKid()),
            [
                'exp' => $actualToken->getExp(),
                'iat' => $actualToken->getIat(),
            ]
        );

        if (!$actualToken->equals($expectedToken)) {
            throw new \InvalidArgumentException('PbjxTokens do not match.', Code::INVALID_ARGUMENT);
        }
    }

    /**
     * @param string $kid
     *
     * @return string
     */
    private function getSecret(string $kid): string
    {
        if (isset($this->keys[$kid])) {
            return $this->keys[$kid];
        }

        if ('bearer' === $kid) {
            $secret = $this->getAccessToken();
            if (null !== $secret) {
                return $secret;
            }
        }

        throw new \InvalidArgumentException('PbjxTokenSigner given unknown kid.', Code::INVALID_ARGUMENT);
    }

    /**
     * Attempts to get the access token from the bearer
     * which would have been populated into symfony's
     * TokenStorage service during authentication.
     *
     * @return string
     */
    private function getAccessToken(): ?string
    {
        if (null === $this->tokenStorage) {
            return null;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $accessToken = trim(str_replace('Bearer ', '', (string)$token->getCredentials()));
        return empty($accessToken) ? null : $accessToken;
    }
}
