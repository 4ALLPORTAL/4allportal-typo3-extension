<?php

declare(strict_types=1);

namespace Fourallportal\Fourallportalext\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Restricts API access to members of the frontend user groups configured in
 * the extension setting `allowedFrontendUserGroups`.
 *
 * An empty setting allows every active frontend user, which keeps existing
 * installations with a dedicated connector account working unchanged. Only
 * direct group membership counts, subgroups are not resolved.
 */
final class FrontendUserGroupPolicy
{
    private const EXTENSION_KEY = 'fourallportal_typo3_extension';
    private const SETTING = 'allowedFrontendUserGroups';

    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration)
    {
    }

    /**
     * @param array<string, mixed> $user fe_users row
     */
    public function allows(array $user): bool
    {
        $allowedGroups = $this->allowedGroups();

        if ([] === $allowedGroups) {
            return true;
        }

        $userGroups = GeneralUtility::intExplode(',', (string) ($user['usergroup'] ?? ''), true);

        return [] !== array_intersect($allowedGroups, $userGroups);
    }

    /**
     * @return list<int>
     */
    private function allowedGroups(): array
    {
        try {
            $value = $this->extensionConfiguration->get(self::EXTENSION_KEY, self::SETTING);
        } catch (Exception) {
            // not configured yet (e.g. settings never saved) - same as empty
            return [];
        }

        return GeneralUtility::intExplode(',', (string) $value, true);
    }
}
