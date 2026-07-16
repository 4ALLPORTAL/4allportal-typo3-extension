<?php

use Fourallportal\Fourallportalext\Middleware\ApiMiddleware;

return [
    'frontend' => [
        'fourallportal/typo3-extension/api' => [
            'target' => ApiMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                // API access uses bearer tokens instead of frontend sessions
                'typo3/cms-frontend/authentication',
                // keep language-base redirects (/api/... -> /en/api/...) away from API paths
                'typo3/cms-frontend/base-redirect-resolver',
            ],
        ],
    ],
];
