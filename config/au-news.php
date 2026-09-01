<?php

return [
    'base_url' => 'https://au.int',

    'listing_url' => 'https://au.int/en/happening',

    'timeout' => 20,

    'connect_timeout' => 10,

    'user_agent' => env(
        'AU_NEWS_USER_AGENT',
        'AU-Handbook-CMS/1.0'
    ),

    'default_status' => env(
        'AU_NEWS_DEFAULT_STATUS',
        'review'
    ),
];
