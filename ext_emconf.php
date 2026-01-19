<?php

$EM_CONF[$_EXTKEY] = [
    'title' => '4ALLPORTAL Rest-Api extension',
    'description' => '4ALLPORTAL Rest-Api extension for nnrestapi',
    'category' => 'frontend',
    'author' => '4allportal.com',
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
