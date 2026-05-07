# Report Generator

A web application for generating developer reports. It synchronizes commits from GitLab and tasks from Bitrix24, links them, generates narrative descriptions via an LLM (Claude / OpenAI) and exports `.docx`.

## Commands

All commands are run from the repository root through `make`. The containers must be up (`make up`) before running anything else.

```bash
make up          # start containers (docker compose up -d)
make down        # stop containers
make build       # rebuild images
make migrate     # apply migrations (php artisan migrate)
make test        # PHPUnit — all tests (vendor/bin/phpunit)
make lint        # all checks: Pint, PHPStan, ESLint, Prettier
make fix         # auto-fix: Pint + Prettier
make shell       # sh shell inside the app container
make logs        # docker compose logs -f
```

Run a single test from inside the container:

```bash
docker compose exec app sh -c "cd /var/www && vendor/bin/phpunit --filter=TestName"
```

Run only PHPStan:

```bash
docker compose exec app sh -c "cd /var/www && vendor/bin/phpstan analyse --no-progress"
```

Run only ESLint:

```bash
docker compose exec node sh -c "cd /app && npx eslint src/"
```

## Stack

- **Backend:** PHP 8.2+ / Laravel 12, strict typing (`declare(strict_types=1)`)
- **Frontend:** React 18.3 / TypeScript 5.6 / Vite 5.4, React Router 7, TanStack Query 5
- **Database:** SQLite (file at `backend/database/database.sqlite`)
- **Queues:** Redis 7 + Laravel Queue (the `worker` container)
- **Export:** PHPWord for `.docx` generation
- **LLM:** Anthropic Claude API / OpenAI API (Strategy pattern via `LlmProviderInterface`)
- **Containerization:** Docker Compose — 4 services: `app` (:8000), `node` (:5173), `worker`, `redis` (:6379)

## Code style

### Backend (PHP)

The style is defined in `backend/pint.json` (PSR-12 preset). Static analysis — PHPStan level 10 with Larastan (`backend/phpstan.neon`). Key rules:

- `declare(strict_types=1)` in every PHP file
- Typed parameters, returns, and properties — no `mixed`, no error suppression via `@phpstan-ignore`
- Architecture — DDD: `backend/app/Domain/<Context>/` for business logic, `backend/app/Infrastructure/<Context>/` for adapters to external systems (see "Domain and Infrastructure layers")
- Use Cases are `final readonly` Action classes with a single public `__invoke()` method (see `Domain/<Context>/Actions/`)
- External clients implement an interface from `Domain/<Context>/Services/` (e.g., `Bitrix24ClientInterface` → `Infrastructure/Bitrix24/Bitrix24Client`)
- Enums for every enumerable value (see `Domain/<Context>/Enums/`)
- DTOs are `readonly` classes (see `Domain/<Context>/DTOs/`)
- Value Objects live in `Domain/<Context>/ValueObjects/` or `Domain/Shared/ValueObjects/`
- Service providers register interface → implementation bindings

Action class example:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Events\BranchMatched;
use App\Domain\Matching\Models\MatchResult;

final readonly class MatchBranch
{
    public function __invoke(Branch $branch): MatchResult
    {
        [$task, $confidence] = $this->resolve($branch);

        $matchResult = $this->createOrUpdateMatch($branch, $task, $confidence);

        BranchMatched::dispatch($matchResult, $branch);

        return $matchResult;
    }

    // ...
}
```

Infrastructure client example:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Bitrix24;

use App\Domain\Bitrix24\Services\Bitrix24ClientInterface;

final class Bitrix24Client implements Bitrix24ClientInterface
{
    // ...
}
```

### Frontend (TypeScript)

ESLint config — `frontend/eslint.config.js`. Rules:

- TypeScript strict mode, no `any`
- Functional components with hooks, no classes
- API clients live in `frontend/src/api/`, one module per domain (`sync`, `reports`, `inbox`, `settings`)
- Hooks live in `frontend/src/hooks/`, wrapping TanStack Query (`useQuery` / `useMutation`)
- API types — in `frontend/src/types/api.ts`

## Testing

Backend tests live in `backend/tests/`. Tests use in-memory SQLite (`DB_DATABASE=:memory:`). JSON fixtures for external-API mocks — in `backend/tests/Fixtures/`. The LLM provider mock — `backend/tests/Mocks/MockLlmProvider.php`.

When adding new functionality, write both unit and feature tests. Mocks for external services (GitLab, Bitrix24, LLM) are mandatory: tests must not make real HTTP requests.

The test layout mirrors the DDD structure: `tests/Unit/Domain/<Context>/Actions/`, `tests/Unit/Domain/<Context>/Models/`, `tests/Unit/Domain/<Context>/Queries/`, `tests/Unit/Domain/<Context>/ValueObjects/`. Feature tests live in `tests/Feature/<Area>/`.

Feature test pattern:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Domain\Narrative\Actions\GenerateNarrativesForReport;
use App\Domain\Narrative\Services\LlmProviderInterface;
use App\Domain\Report\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

final class GenerateNarrativesForReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_narratives_for_report(): void
    {
        // GIVEN: an LLM-provider mock and a report with linked tasks
        $this->app->bind(LlmProviderInterface::class, MockLlmProvider::class);
        $report = Report::factory()->withTasks(3)->create();

        // WHEN: we run narrative generation through the Action
        $action = $this->app->make(GenerateNarrativesForReport::class);
        $action($report);

        // THEN: narratives are recorded in the history
        $this->assertDatabaseCount('narrative_history', 3);
    }
}
```

Key rules: GIVEN-WHEN-THEN structure in comments, `final class`, mock external services through the container (LLM via `MockLlmProvider`), factories for test data, JSON fixtures for external-API responses in `backend/tests/Fixtures/`.

Before committing, make sure all checks pass:

```bash
make lint && make test
```

## Git workflow

- Commit format: [Conventional Commits](https://www.conventionalcommits.org/) — `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
- One logical task per PR
- Before opening a PR: `make lint && make test` with no errors
- Code and comments — in English

## Architecture decisions

### Data flow

Main data flow during report generation:

1. **Synchronization** — `RunSyncJob` (Redis queue) runs the `Domain/Sync/Actions/SyncGitLab` and `SyncBitrix24` actions (with sub-steps `SyncBitrix24Tasks`, `SyncBitrix24TimeEntries`), which fetch data through the `Infrastructure/GitLab/GitLabClient` and `Infrastructure/Bitrix24/Bitrix24Client` clients and store it in SQLite. When done, the `SyncCompleted` event fires.
2. **Matching** — the `MatchBranchesOnSyncCompleted` listener runs the `Domain/Matching/Actions/MatchBranch` (one branch at a time) or `MatchAllUnmatched` (in bulk) actions. The branch name is parsed by `Domain/GitLab/Services/BranchParser`, the matching `Bitrix24\Models\Task` is found by task number, and confidence is the `ConfidenceLevel` enum. Unmatched commits land in the Inbox (`Domain/Inbox/Queries/GetUnlinkedBranches`).
3. **Report assembly** — the `Domain/Report/Actions/GenerateReport` action assembles data (tasks + commits + time entries) into the report structure and emits the `ReportGenerated` event.
4. **Narrative** — the `GenerateNarrativesOnReportGenerated` listener runs the `Domain/Narrative/Actions/GenerateNarrativesForReport` action, which goes through `LlmProviderInterface` (implementations in `Infrastructure/LLM/` — `LlmManager` selects `ClaudeProvider` / `OpenAiProvider`) to obtain human-readable descriptions. Manual editing (`EditDayNarrative`, `EditTaskNarrative`) and regeneration (`Regenerate*Narrative`) are supported, with entries written to `NarrativeHistory`.
5. **Export** — `Infrastructure/Report/WordExporter` (an implementation of `Domain/Report/Services/ReportExporterInterface`, using PHPWord) generates the final `.docx` file. Empty days are handled by `addEmptyDayCell()`; the day source is marked with the `ReportDaySource` enum.

The user drives the process via the React frontend: triggers synchronization, reviews matches in the Inbox, fixes links, and then generates and downloads the report.

### Domain and Infrastructure layers

The backend is organized along DDD lines: business logic — in `backend/app/Domain/<Context>/`, adapters to external systems — in `backend/app/Infrastructure/<Context>/`.

**`backend/app/Domain/`** — 9 bounded contexts, each with its own structure (`Actions/`, `DTOs/`, `Enums/`, `Events/`, `Listeners/`, `Models/`, `Queries/`, `Services/` (contracts), `ValueObjects/` — selectively):

- `GitLab/` — models `Branch`, `Commit`; parsers `BranchParser`, `ConventionalCommitParser`; contract `GitLabClientInterface`; DTO `ParsedBranch`
- `Bitrix24/` — models `Task`, `TimeEntry`; contract `Bitrix24ClientInterface`; enums `TaskStatus`, `ParticipationRole`
- `Matching/` — actions `MatchBranch`, `MatchAllUnmatched`, `RematchBranch`; model `MatchResult`; enum `ConfidenceLevel`; event `BranchMatched` + listener `MatchBranchesOnSyncCompleted`
- `Narrative/` — actions `GenerateNarrativesForReport`, `EditDay/TaskNarrative`, `RegenerateDay/TaskNarrative`, `UndoDay/TaskNarrative`; model `NarrativeHistory`; contract `LlmProviderInterface`; helper `NarrativeSupport`
- `Report/` — action `GenerateReport`; models `Report`, `ReportDay`, `ReportTask`, `ReportDayTask`; queries `GetReportPreview`, `GetMonthlyReportData`, `GetTaskTimeBreakdown` and others; contracts `ReportExporterInterface`, `PromptExportServiceInterface`; enums `ReportType`, `ReportStatus`, `ReportDaySource`; ValueObject `Narrative`
- `Sync/` — actions `SyncGitLab`, `SyncBitrix24` (+ `SyncBitrix24Tasks`, `SyncBitrix24TimeEntries`, `SyncBitrix24ForReport`, `EnsureTasksForPeriod`); models `SyncJob`, `SyncLog`; event `SyncCompleted`
- `Inbox/` — actions `AssignBranch`, `BulkAssignBranches`, `CreateTaskAndAssign`, `IgnoreBranch`; query `GetUnlinkedBranches`
- `Settings/` — model `Setting`
- `Shared/` — reusable ValueObjects `DateRange`, `TaskNumber`

**`backend/app/Infrastructure/`** — implementations of domain contracts and adapters to external APIs:

- `Bitrix24/Bitrix24Client.php` — implementation of `Domain\Bitrix24\Services\Bitrix24ClientInterface`
- `GitLab/GitLabClient.php` — implementation of `Domain\GitLab\Services\GitLabClientInterface`
- `LLM/` — `LlmManager` (provider dispatcher), `ClaudeProvider`, `OpenAiProvider` (all implement `Domain\Narrative\Services\LlmProviderInterface`)
- `Report/` — `WordExporter`, `PromptExportService` (implementations of the same-named interfaces from `Domain/Report/Services/`)

**`backend/app/Models/`** — only `User.php`. All domain models live in `Domain/<Context>/Models/`.

**`backend/app/Http/Controllers/`** — a thin layer (`InboxController`, `ReportController`, `SettingsController`, `SyncController`): accept the request → invoke an Action / Query → return an API Resource.

Background synchronization runs through `App\Jobs\RunSyncJob` (Laravel Queue, Redis).

## Boundaries

- Never commit secrets, tokens or API keys. All secrets — only via `.env` files, which are in `.gitignore`
- Do not modify files under `backend/vendor/` or `frontend/node_modules/`
- Do not change the PHPStan level or the Pint preset without explicit agreement
- Do not disable or weaken ESLint / TypeScript strict rules
- Do not add dependencies without a reason — keep external packages to a minimum
- Do not make real HTTP calls in tests — use mocks only
- Do not delete or skip failing tests — fix them
