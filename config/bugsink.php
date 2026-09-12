<?php

return [
    // Base URL of the self-hosted Bugsink instance, e.g. https://bugsink.example.com
    'url' => env('BUGSINK_URL'),

    // A Bugsink Auth Token (Tokens page in the Bugsink UI). Grants whole-installation
    // access — store it as a secret, never commit it.
    'token' => env('BUGSINK_API_TOKEN'),

    // Default project ID used when --project is not passed to bugsink:read.
    'project_id' => (int) env('BUGSINK_PROJECT_ID', 1),
];
