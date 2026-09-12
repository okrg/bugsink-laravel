# Bugsink Laravel

Artisan command and service provider for pulling issues from a self-hosted
[Bugsink](https://www.bugsink.com/) instance's canonical REST API
(`/api/canonical/0/`). Bugsink is a lightweight, Sentry-SDK-compatible,
self-hosted error tracker.

This package is a **pull** integration, not a webhook. Bugsink Messaging
Service webhooks are optional real-time push and are not required for this
package to work; they can and do fail silently, so treat the API as the
source of truth and webhooks as a bonus.

## Why pull instead of webhook

- The canonical API is stable, authenticated, and paginated.
- A pull works on demand, from a scheduled job, from an agent/MCP tool, or in
  CI — a webhook only works when the receiving endpoint is alive.
- There is no official Bugsink MCP server; a community one
  ([`bugsink-mcp`](https://github.com/j-shelfwood/bugsink-mcp)) wraps this
  same canonical API. The official `sentry-mcp` does **not** work against
  Bugsink.

## Installation

```bash
composer require okrg/bugsink-laravel
```

The service provider is auto-discovered. Publish the config if you want to
version-control overrides (env vars work without publishing):

```bash
php artisan vendor:publish --tag=bugsink-config
```

## Configuration

Set these in `.env`:

```env
BUGSINK_URL=https://bugsink.example.com
BUGSINK_API_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
BUGSINK_PROJECT_ID=1
BUGSINK_REPORT_PATH=storage/app/bugsink-report.md
```

`BUGSINK_REPORT_PATH` is optional; it defaults to
`storage_path('app/bugsink-report.md')`, which Laravel's default
`storage/app/.gitignore` already excludes from version control. The file is
fully regenerated on every run — never hand-edit it.

Generate a token from the Bugsink "Tokens" page, or via
`bugsink-manage create_auth_token`. Tokens currently grant whole-installation
access — store them as secrets, never commit them, never print them in logs
or reports. Rotate immediately if one is ever exposed: create a replacement
token, update the secret, delete the old token, then re-verify a read.

## Usage

```bash
php artisan bugsink:read
php artisan bugsink:read --project=2 --limit=10
php artisan bugsink:read --json
php artisan bugsink:read --report=/custom/path/report.md
php artisan bugsink:read --no-report
```

Every run writes a Markdown report to `config('bugsink.report_path')`
(override per-run with `--report=`, skip with `--no-report`). This is the
standard, plain-text, referenceable output of a pull — check it into your
own notes/tooling if you want a durable snapshot; the path itself is always
regenerated, not appended to. With `--json`, the same path is echoed back as
`report_path` in the JSON payload instead of a separate console line, so
stdout stays strictly parseable.

`bugsink:read` is an **on-demand snapshot**: one request to `/issues/`,
sorted by `last_seen` descending, taking the first `--limit` results
client-side. It does not paginate past the first page and does not persist a
`last_seen` cursor between runs. That is enough for interactive checks and
for an AI agent/coding assistant to answer "what's new in Bugsink." It is
**not** by itself a complete scheduled-triage job — a cron/queue job that
needs guaranteed no-gap coverage should page through `next` links and persist
the last seen cursor per project.

## What this does not do

- Does not configure Bugsink alert/messaging services (Slack, Discord,
  Mattermost). Configure and **test** those separately in the Bugsink UI if
  you also want real-time push.
- Does not send data *to* Bugsink. Error capture is the Sentry-compatible SDK
  (`sentry/sentry-laravel`) pointed at your Bugsink DSN; this package only
  reads issues back out.
- Does not implement pagination or a persisted cursor (see above).

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT.
