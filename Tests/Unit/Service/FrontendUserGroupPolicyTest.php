<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Tests\Unit\Service;

use Fourallportal\Fourallportalext\Service\FrontendUserGroupPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Unit tests for the allowed frontend user group restriction. The real
 * ExtensionConfiguration is used, it reads from TYPO3_CONF_VARS.
 */
final class FrontendUserGroupPolicyTest extends TestCase
{
    private FrontendUserGroupPolicy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new FrontendUserGroupPolicy(new ExtensionConfiguration());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
        parent::tearDown();
    }

    #[Test]
    public function emptySettingAllowsEveryUser(): void
    {
        $this->configureAllowedGroups('');

        self::assertTrue($this->subject->allows(['usergroup' => '']));
        self::assertTrue($this->subject->allows(['usergroup' => '7']));
    }

    #[Test]
    public function memberOfAnAllowedGroupIsAllowed(): void
    {
        $this->configureAllowedGroups('3, 5');

        self::assertTrue($this->subject->allows(['usergroup' => '1,5']));
    }

    #[Test]
    public function userOutsideAllowedGroupsIsRejected(): void
    {
        $this->configureAllowedGroups('3,5');

        self::assertFalse($this->subject->allows(['usergroup' => '1,4']));
        self::assertFalse($this->subject->allows(['usergroup' => '']));
        self::assertFalse($this->subject->allows([]));
    }

    #[Test]
    public function groupUidsAreComparedAsIntegersNotSubstrings(): void
    {
        $this->configureAllowedGroups('1');

        self::assertFalse($this->subject->allows(['usergroup' => '10,11']));
    }

    private function configureAllowedGroups(string $value): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['fourallportal_typo3_extension']['allowedFrontendUserGroups'] = $value;
    }
}
