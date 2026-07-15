<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Service;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Exception\Crypto\InvalidHashStringException;

/**
 * Issues and validates stateless bearer tokens for the file API.
 *
 * A token is a base64url-encoded JSON payload (fe_user uid, expiry timestamp
 * and a fingerprint of the user's password hash) signed with an HMAC based on
 * the TYPO3 encryption key. No server-side session state is required; the
 * connector re-authenticates automatically on 401/403.
 */
final class TokenService
{
    public const DEFAULT_TTL_SECONDS = 3600;
    private const HMAC_CONTEXT = 'fourallportal-api-token';

    public function __construct(private readonly HashService $hashService)
    {
    }

    /**
     * @param string $passwordHash the fe_user's stored password hash, bound
     *                             into the token so it expires on password change
     */
    public function issue(
        int $frontendUserUid,
        string $passwordHash,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): string {
        $payload = (string) json_encode([
            'uid' => $frontendUserUid,
            'exp' => time() + $ttlSeconds,
            'pwf' => $this->passwordFingerprint($passwordHash),
        ]);

        return $this->hashService->appendHmac($this->base64UrlEncode($payload), self::HMAC_CONTEXT);
    }

    /**
     * Returns the token payload (`uid` and password fingerprint `pwf`) for a
     * valid token, or null if it is tampered with, malformed or expired.
     *
     * @return array{uid: int, pwf: string}|null
     */
    public function validate(string $token): ?array
    {
        try {
            $encodedPayload = $this->hashService->validateAndStripHmac($token, self::HMAC_CONTEXT);
        } catch (InvalidHashStringException) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 3);

        if (!is_array($payload)) {
            return null;
        }

        $uid = (int) ($payload['uid'] ?? 0);
        $expiresAt = (int) ($payload['exp'] ?? 0);
        $fingerprint = (string) ($payload['pwf'] ?? '');

        if ($uid <= 0 || '' === $fingerprint || $expiresAt < time()) {
            return null;
        }

        return ['uid' => $uid, 'pwf' => $fingerprint];
    }

    /**
     * Whether the token was issued for the given (current) password hash.
     * A password change rotates the hash and thus invalidates old tokens.
     */
    public function matchesPassword(array $payload, string $currentPasswordHash): bool
    {
        return hash_equals($this->passwordFingerprint($currentPasswordHash), (string) ($payload['pwf'] ?? ''));
    }

    private function passwordFingerprint(string $passwordHash): string
    {
        // keyed HMAC (encryption key + context) so the fingerprint in the
        // readable token payload leaks nothing about the stored password hash
        $secret = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '').self::HMAC_CONTEXT;

        return substr(hash_hmac('sha256', $passwordHash, $secret), 0, 32);
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
