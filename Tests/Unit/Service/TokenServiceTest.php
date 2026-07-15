<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Tests\Unit\Service;

use Fourallportal\Fourallportalext\Service\TokenService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Unit tests for the stateless bearer token: issue/validate round-trip,
 * tampering, expiry and password-change revocation. HashService is used for
 * real (it only needs the encryption key) so the HMAC path is exercised
 * end to end.
 */
final class TokenServiceTest extends TestCase
{
    private const PASSWORD_HASH = '$argon2i$v=19$m=65536,t=16,p=1$examplehashvalue';

    private TokenService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 64);
        $this->subject = new TokenService(new HashService());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
        parent::tearDown();
    }

    #[Test]
    public function validateReturnsUidForFreshlyIssuedToken(): void
    {
        $token = $this->subject->issue(42, self::PASSWORD_HASH);

        $payload = $this->subject->validate($token);

        self::assertNotNull($payload);
        self::assertSame(42, $payload['uid']);
        self::assertNotSame('', $payload['pwf']);
    }

    #[Test]
    public function validateRejectsTamperedToken(): void
    {
        $token = $this->subject->issue(42, self::PASSWORD_HASH);

        self::assertNull($this->subject->validate($token . 'x'));
        self::assertNull($this->subject->validate(substr($token, 1)));
    }

    #[Test]
    public function validateRejectsGarbageInput(): void
    {
        self::assertNull($this->subject->validate(''));
        self::assertNull($this->subject->validate('not-a-token'));
    }

    #[Test]
    public function validateRejectsExpiredToken(): void
    {
        $token = $this->subject->issue(42, self::PASSWORD_HASH, -1);

        self::assertNull($this->subject->validate($token));
    }

    #[Test]
    public function matchesPasswordAcceptsUnchangedHash(): void
    {
        $token = $this->subject->issue(42, self::PASSWORD_HASH);
        $payload = $this->subject->validate($token);

        self::assertTrue($this->subject->matchesPassword($payload, self::PASSWORD_HASH));
    }

    #[Test]
    public function matchesPasswordRejectsChangedHash(): void
    {
        $token = $this->subject->issue(42, self::PASSWORD_HASH);
        $payload = $this->subject->validate($token);

        self::assertFalse($this->subject->matchesPassword($payload, '$argon2i$v=19$m=65536,t=16,p=1$aDIFFERENThash'));
    }

    #[Test]
    public function tokenSignedWithADifferentKeyIsRejected(): void
    {
        $token = $this->subject->issue(42, self::PASSWORD_HASH);

        // simulate a different installation / rotated encryption key
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('b', 64);
        $foreign = new TokenService(new HashService());

        self::assertNull($foreign->validate($token));
    }
}
