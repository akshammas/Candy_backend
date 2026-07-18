<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // TODO: replace with your real frontend domain(s) once deployed,
    // e.g. ['https://www.cymtr.com']. Wildcard "*" will NOT work here
    // because supports_credentials is true — CORS requires an exact
    // origin match whenever cookies/credentials are involved.
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://127.0.0.1:5500'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
