<?php

namespace Okrg\BugsinkLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BugsinkReadCommand extends Command
{
    protected $signature = 'bugsink:read
                            {--project= : Bugsink project ID}
                            {--limit=25 : Maximum number of issues to show}
                            {--json : Output the Bugsink response as JSON}';

    protected $description = 'Read recent Bugsink issues for a project';

    public function handle(): int
    {
        $baseUrl = config('bugsink.url');
        $token = config('bugsink.token');
        $projectId = $this->option('project') ?? config('bugsink.project_id');
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($token) || $token === '') {
            $this->error('BUGSINK_URL and BUGSINK_API_TOKEN must be configured.');

            return self::FAILURE;
        }

        if ($limit === false) {
            $this->error('--limit must be a positive integer.');

            return self::FAILURE;
        }

        $response = $this->client($baseUrl, $token)->get('issues/', [
            'project' => $projectId,
            'sort' => 'last_seen',
            'order' => 'desc',
        ]);

        if (! $response->successful()) {
            $this->error("Bugsink request failed [{$response->status()}].");

            return self::FAILURE;
        }

        $issues = collect($response->json('results', []))
            ->take($limit)
            ->values();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'results' => $issues,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($issues->isEmpty()) {
            $this->info('No Bugsink issues found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Last seen', 'Events', 'Type', 'Error'],
            $issues->map(fn (array $issue): array => [
                $issue['friendly_id'] ?? $issue['id'] ?? '',
                $issue['last_seen'] ?? '',
                $issue['digested_event_count'] ?? '',
                $issue['calculated_type'] ?? '',
                $issue['calculated_value'] ?? $issue['title'] ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function client(string $baseUrl, string $token): PendingRequest
    {
        return Http::baseUrl(rtrim($baseUrl, '/').'/api/canonical/0')
            ->withToken($token)
            ->acceptJson()
            ->timeout(15);
    }
}
