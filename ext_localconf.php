<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTypoScriptSetup(
    '@import "EXT:fourallportalext/Configuration/TypoScript/setup.typoscript"'
);
