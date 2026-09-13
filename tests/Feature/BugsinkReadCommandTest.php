<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->reportPath = sys_get_temp_dir().'/bugsink-laravel-tests-'.uniqid().'/report.md';

    config()->set('bugsink', [
        'url' => 'https://bugsink.test',
        'token' => 'test-token',
        'project_id' => 1,
        'report_path' => $this->reportPath,
    ]);
});

afterEach(function (): void {
    if (is_dir($dir = dirname($this->reportPath))) {
        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }
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

it('writes a Markdown report to the configured default path', function () {
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

    $this->artisan('bugsink:read')->assertSuccessful();

    expect($this->reportPath)->toBeFile();
    expect(file_get_contents($this->reportPath))
        ->toContain('# Bugsink report')
        ->toContain('BLISS-42')
        ->toContain('Save failed');
});

it('skips writing the report file when --no-report is passed', function () {
    Http::fake([
        'https://bugsink.test/api/canonical/0/issues/*' => Http::response(['results' => []]),
    ]);

    $this->artisan('bugsink:read', ['--no-report' => true])->assertSuccessful();

    expect($this->reportPath)->not->toBeFile();
});

it('honors a --report override path', function () {
    Http::fake([
        'https://bugsink.test/api/canonical/0/issues/*' => Http::response(['results' => []]),
    ]);

    $overridePath = dirname($this->reportPath).'/override.md';

    $this->artisan('bugsink:read', ['--report' => $overridePath])->assertSuccessful();

    expect($overridePath)->toBeFile();
    expect($this->reportPath)->not->toBeFile();

    unlink($overridePath);
});

it('emits strictly parseable JSON on stdout, uncorrupted by report-write status, and includes report_path', function () {
    Http::fake([
        'https://bugsink.test/api/canonical/0/issues/*' => Http::response([
            'results' => [
                ['friendly_id' => 'BLISS-42'],
            ],
        ]),
    ]);

    $exitCode = \Illuminate\Support\Facades\Artisan::call('bugsink:read', ['--json' => true]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($exitCode)->toBe(0);

    $decoded = json_decode($output, associative: true, flags: JSON_THROW_ON_ERROR);

    expect($decoded['report_path'])->toBe($this->reportPath);
    expect($decoded['results'][0]['friendly_id'])->toBe('BLISS-42');
    expect($this->reportPath)->toBeFile();
});

it('sanitizes pipes and line breaks in issue fields so the Markdown table cannot break', function () {
    Http::fake([
        'https://bugsink.test/api/canonical/0/issues/*' => Http::response([
            'results' => [
                [
                    'friendly_id' => 'BLISS-42',
                    'last_seen' => '2026-09-11T20:45:00Z',
                    'digested_event_count' => 1,
                    'calculated_type' => 'RuntimeException',
                    'calculated_value' => "Save failed | disk full\r\nsecond line\nthird line",
                ],
            ],
        ]),
    ]);

    $this->artisan('bugsink:read')->assertSuccessful();

    $report = file_get_contents($this->reportPath);
    $rows = array_values(array_filter(explode("\n", trim($report))));
    $lastRow = end($rows);

    // Un-escape literal pipes before splitting, so only real column
    // delimiters are counted: exactly 5 columns, i.e. 7 explode() segments
    // (2 empty boundary segments either side of the leading/trailing "|").
    $fields = explode('|', str_replace('\\|', '§', $lastRow));

    expect($fields)->toHaveCount(7);
    expect($report)->toContain('Save failed \\| disk full second line third line');
    expect($report)->not->toContain("\r");
});

it('fails the command when the report file cannot be written', function () {
    Http::fake([
        'https://bugsink.test/api/canonical/0/issues/*' => Http::response(['results' => []]),
    ]);

    mkdir($this->reportPath, recursive: true);

    $this->artisan('bugsink:read')
        ->expectsOutputToContain('Failed to write Bugsink report')
        ->assertFailed();

    rmdir($this->reportPath);
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
