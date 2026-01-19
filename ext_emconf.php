<?php

$EM_CONF[$_EXTKEY] = [
    'title' => '4ALLPORTAL extension',
    'description' => 'This TYPO3 extension enables the 4allportal-typo3-connector to send files from 4ALLPORTAL to TYPO3.',
    'category' => 'frontend',
    'author' => '4allportal.com',
    'author_company' => '4ALLPORTAL GmbH',
    'version' => '1.0.0',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.9.99',
            'nnhelpers' => '13.0.0-13.9.99',
            'nnrestapi' => '13.0.0-13.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
