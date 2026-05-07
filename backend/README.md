# Backend — Report Generator

The Laravel backend of the Report Generator application. Business logic is organized along DDD lines: `Domain/` (bounded contexts) + `Infrastructure/` (external-service adapters).

A full architecture walkthrough lives in the root [AGENTS.md](../AGENTS.md). Setup and how to run the environment are in the root [README.md](../README.md).

## Stack

- PHP `^8.2`, the `ext-redis` extension
- Laravel `^12.0`, `laravel/tinker` `^2.10.1`
- PHPWord `^1.4` (`.docx` generation)
- PHPUnit `^11.5.50` (tests on in-memory SQLite)
- Larastan `^3.9` (PHPStan level 10), Laravel Pint `^1.29`
- Mockery `^1.6`, FakerPHP `^1.23`, Collision `^8.6`, Pail `^1.2.2`, Sail `^1.41`

Versions — from [composer.json](composer.json).

## Layout of `app/`

```
app/
├── Domain/<Context>/{Actions, DTOs, Enums, Events, Listeners, Models, Queries, Services, ValueObjects}
├── Infrastructure/{Bitrix24, GitLab, LLM, Report}
├── Http/Controllers/
├── Jobs/
├── Console/Commands/
└── Models/User.php
```

**Bounded contexts (`Domain/`):** `Bitrix24`, `GitLab`, `Inbox`, `Matching`, `Narrative`, `Report`, `Settings`, `Shared`, `Sync`. Subfolders inside a context are created as needed — not every context contains all nine.

**Infrastructure (`Infrastructure/`):**

- `Bitrix24/Bitrix24Client` — Bitrix24 HTTP client
- `GitLab/GitLabClient` — GitLab HTTP client
- `LLM/{LlmManager, ClaudeProvider, OpenAiProvider}` — LLM providers
- `Report/{WordExporter, PromptExportService}` — `.docx` export and prompt dump

`App\Models\User` intentionally stays in `app/Models/` (a Laravel auth requirement). Domain models live in `Domain/<Context>/Models/`.

A detailed description of context boundaries, data flows and the patterns we follow lives in [AGENTS.md](../AGENTS.md).

## Running

Installation, bringing up the docker environment and the frontend — through the root [Makefile](../Makefile). See the root [README.md](../README.md).

Backend-specific commands:

- `make migrate` — apply migrations
- `make test` — PHPUnit (in-memory SQLite)
- `make lint` — Pint + PHPStan (level 10)
- `make fix` — Pint auto-fixes
- Run a specific test: `docker exec moronocracy-backend php artisan test --filter=TestName`

## Testing

- `tests/Unit/Domain/<Context>/...` — mirrors the DDD layout; covers Actions, Services, ValueObjects, Queries
- `tests/Feature/...` — HTTP / integration tests for controllers and jobs
- In-memory SQLite (see `phpunit.xml`)
- `tests/Mocks/MockLlmProvider.php` — a deterministic LLM mock
- `tests/Fixtures/` — request/response fixtures for external APIs

Test examples and accepted conventions — in [AGENTS.md](../AGENTS.md).

## See also

- [AGENTS.md](../AGENTS.md) — architecture, contexts, patterns, testing
- [README.md](../README.md) — install, run, Makefile commands
- [CONTRIBUTING.md](../CONTRIBUTING.md) — the contribution process
