<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Service;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Exception\Crypto\InvalidHashStringException;

/**
 * Issues and validates stateless bearer tokens for the file API.
 *
 * A token is a base64url-encoded JSON payload (fe_user uid + expiry timestamp)
 * signed with an HMAC based on the TYPO3 encryption key. No server-side session
 * state is required; the connector re-authenticates automatically on 401/403.
 */
final class TokenService
{
    public const int DEFAULT_TTL_SECONDS = 3600;
    private const string HMAC_CONTEXT = 'fourallportal-api-token';

    public function __construct(private readonly HashService $hashService)
    {
    }

    public function issue(int $frontendUserUid, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): string
    {
        $payload = (string)json_encode([
            'uid' => $frontendUserUid,
            'exp' => time() + $ttlSeconds,
        ]);

        return $this->hashService->appendHmac($this->base64UrlEncode($payload), self::HMAC_CONTEXT);
    }

    /**
     * Returns the fe_user uid for a valid token, or null if the token
     * is tampered with, malformed or expired.
     */
    public function validate(string $token): ?int
    {
        try {
            $encodedPayload = $this->hashService->validateAndStripHmac($token, self::HMAC_CONTEXT);
        } catch (InvalidHashStringException) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 2);

        if (!is_array($payload)) {
            return null;
        }

        $uid = (int)($payload['uid'] ?? 0);
        $expiresAt = (int)($payload['exp'] ?? 0);

        if ($uid <= 0 || $expiresAt < time()) {
            return null;
        }

        return $uid;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
