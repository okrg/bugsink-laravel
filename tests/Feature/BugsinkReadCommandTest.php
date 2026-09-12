<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('bugsink', [
        'url' => 'https://bugsink.test',
        'token' => 'test-token',
        'project_id' => 1,
    ]);
});

it('lists recent Bugsink issues with bearer authentication', function () {
    Http::fake([
        'https://bugsink.test/api/canonical/0/issues/*' => Http::response([
            'results' => [
                [
                    'friendly_id' => 'BLISS-42',
                    'last_seen' => '2026-09-11T20:45:00Z',
                    'digested_event_count' => 3,
                    'calculated_type' => 'RuntimeException',
                    'calculated_value' => 'Save failed',
                ],
            ],
        ]),
    ]);

    $this->artisan('bugsink:read')
        ->expectsOutputToContain('BLISS-42')
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->url() === 'https://bugsink.test/api/canonical/0/issues/?project=1&sort=last_seen&order=desc';
    });
});

it('returns the selected issue records as JSON', function () {
    Http::fake([
        'https://bugsink.test/api/canonical/0/issues/*' => Http::response([
            'results' => [
                ['friendly_id' => 'BLISS-42'],
                ['friendly_id' => 'BLISS-43'],
            ],
        ]),
    ]);

    $this->artisan('bugsink:read', ['--json' => true, '--limit' => 1])
        ->expectsOutputToContain('"friendly_id": "BLISS-42"')
        ->doesntExpectOutputToContain('BLISS-43')
        ->assertSuccessful();
});

it('honors a --project override', function () {
    Http::fake([
        'https://bugsink.test/api/canonical/0/issues/*' => Http::response(['results' => []]),
    ]);

    $this->artisan('bugsink:read', ['--project' => 7])->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'project=7'));
});

it('fails without Bugsink API configuration', function () {
    config()->set('bugsink.token', null);

    $this->artisan('bugsink:read')
        ->expectsOutputToContain('BUGSINK_URL and BUGSINK_API_TOKEN must be configured.')
        ->assertFailed();
});
