<?php

$EM_CONF[$_EXTKEY] = [
    'title' => '4ALLPORTAL Rest-Api extension',
    'description' => '4ALLPORTAL Rest-Api extension for nnrestapi',
    'category' => 'frontend',
    'author' => '4allportal.com',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
            'nnhelpers' => '13.0.0-13.99.99',
            'nnrestapi' => '13.0.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
