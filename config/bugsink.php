<?php

return [
    // Base URL of the self-hosted Bugsink instance, e.g. https://bugsink.example.com
    'url' => env('BUGSINK_URL'),

    // A Bugsink Auth Token (Tokens page in the Bugsink UI). Grants whole-installation
    // access — store it as a secret, never commit it.
    'token' => env('BUGSINK_API_TOKEN'),

    // Default project ID used when --project is not passed to bugsink:read.
    'project_id' => (int) env('BUGSINK_PROJECT_ID', 1),

    // Where `bugsink:read` writes its generated Markdown report on every run
    // (override per-run with --report=, or skip entirely with --no-report).
    // Lives under the framework's standard writable runtime directory so it
    // is regenerated on demand and never committed to source control.
    'report_path' => env('BUGSINK_REPORT_PATH', storage_path('app/bugsink-report.md')),
];
