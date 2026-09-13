<?php

namespace Okrg\BugsinkLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class BugsinkReadCommand extends Command
{
    protected $signature = 'bugsink:read
                            {--project= : Bugsink project ID}
                            {--limit=25 : Maximum number of issues to show}
                            {--json : Output the Bugsink response as JSON}
                            {--report= : Path to write the generated Markdown report to (defaults to config("bugsink.report_path"))}
                            {--no-report : Skip writing the Markdown report file}';

    protected $description = 'Read recent Bugsink issues for a project and write a Markdown report';

    public function handle(Filesystem $files): int
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

        $writtenReportPath = null;

        if (! $this->option('no-report')) {
            $reportPath = $this->option('report') ?? config('bugsink.report_path');

            if (is_string($reportPath) && $reportPath !== '') {
                try {
                    $files->ensureDirectoryExists(dirname($reportPath));
                    $bytesWritten = $files->put($reportPath, $this->renderMarkdownReport($projectId, $issues));
                } catch (\ErrorException $exception) {
                    // file_put_contents()/mkdir() report failures (permissions,
                    // disk full, path is a directory, etc.) as PHP warnings,
                    // which Laravel's error handler converts to ErrorException.
                    // Only that failure mode is treated as a report-write
                    // failure; a genuine \Error/\TypeError still propagates.
                    $bytesWritten = false;
                }

                if ($bytesWritten === false) {
                    $this->error("Failed to write Bugsink report to {$reportPath}.");

                    return self::FAILURE;
                }

                $writtenReportPath = $reportPath;

                if (! $this->option('json')) {
                    $this->info("Report written to {$reportPath}");
                }
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'results' => $issues,
                'report_path' => $writtenReportPath,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($issues->isEmpty()) {
            $this->info('No Bugsink issues found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Last seen', 'Events', 'Type', 'Error'],
            $issues->map(fn (array $issue): array => $this->issueRow($issue))->all(),
        );

        return self::SUCCESS;
    }

    private function renderMarkdownReport(mixed $projectId, Collection $issues): string
    {
        $lines = [
            '# Bugsink report',
            '',
            "Generated: {$this->now()}",
            "Project: {$projectId}",
            '',
            'This file is regenerated on every `bugsink:read` run. Do not hand-edit it.',
            '',
        ];

        if ($issues->isEmpty()) {
            $lines[] = 'No Bugsink issues found.';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '| ID | Last seen | Events | Type | Error |';
        $lines[] = '|---|---|---|---|---|';

        foreach ($issues as $issue) {
            $cells = array_map(
                fn (mixed $cell): string => $this->markdownCell($cell),
                $this->issueRow($issue),
            );
            $lines[] = '| '.implode(' | ', $cells).' |';
        }

        return implode("\n", $lines)."\n";
    }

    /** @return array{0: string, 1: string, 2: string, 3: string, 4: string} */
    private function issueRow(array $issue): array
    {
        return [
            $issue['friendly_id'] ?? $issue['id'] ?? '',
            $issue['last_seen'] ?? '',
            $issue['digested_event_count'] ?? '',
            $issue['calculated_type'] ?? '',
            $issue['calculated_value'] ?? $issue['title'] ?? '',
        ];
    }

    private function markdownCell(mixed $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', (string) $value);

        return str_replace('|', '\\|', $value);
    }

    private function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private function client(string $baseUrl, string $token): PendingRequest
    {
        return Http::baseUrl(rtrim($baseUrl, '/').'/api/canonical/0')
            ->withToken($token)
            ->acceptJson()
            ->timeout(15);
    }
}
