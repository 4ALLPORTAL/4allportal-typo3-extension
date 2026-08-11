<?php

$EM_CONF[$_EXTKEY] = [
    'title' => '4ALLPORTAL extension',
    'description' => 'This TYPO3 extension enables the 4allportal-typo3-connector to send files from 4ALLPORTAL to TYPO3.',
    'category' => 'frontend',
    'author' => '4allportal.com',
    'author_company' => '4ALLPORTAL GmbH',
    'version' => '2.0.1',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
