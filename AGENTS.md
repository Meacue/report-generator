# Report Generator

Веб-приложение для генерации отчётов разработчика. Синхронизирует коммиты из GitLab и задачи из Bitrix24, связывает их, генерирует нарративные описания через LLM (Claude / OpenAI) и экспортирует `.docx`.

## Команды

Все команды запускаются из корня репозитория через `make`. Контейнеры должны быть запущены (`make up`) перед выполнением остальных команд.

```bash
make up          # запуск контейнеров (docker compose up -d)
make down        # остановка контейнеров
make build       # пересборка образов
make migrate     # применить миграции (php artisan migrate)
make test        # PHPUnit — все тесты (vendor/bin/phpunit)
make lint        # все проверки: Pint, PHPStan, ESLint, Prettier
make fix         # автоисправление: Pint + Prettier
make shell       # sh-консоль внутри контейнера app
make logs        # docker compose logs -f
```

Запуск отдельного теста из контейнера:

```bash
docker compose exec app sh -c "cd /var/www && vendor/bin/phpunit --filter=ИмяТеста"
```

Запуск только PHPStan:

```bash
docker compose exec app sh -c "cd /var/www && vendor/bin/phpstan analyse --no-progress"
```

Запуск только ESLint:

```bash
docker compose exec node sh -c "cd /app && npx eslint src/"
```

## Стек

- **Backend:** PHP 8.2+ / Laravel 12, строгая типизация (`declare(strict_types=1)`)
- **Frontend:** React 18.3 / TypeScript 5.6 / Vite 5.4, React Router 7, TanStack Query 5
- **БД:** SQLite (файл `backend/database/database.sqlite`)
- **Очереди:** Redis 7 + Laravel Queue (контейнер `worker`)
- **Экспорт:** PHPWord для генерации `.docx`
- **LLM:** Anthropic Claude API / OpenAI API (паттерн Strategy через `LlmProviderInterface`)
- **Контейнеризация:** Docker Compose — 4 сервиса: `app` (:8000), `node` (:5173), `worker`, `redis` (:6379)

## Стиль кода

### Backend (PHP)

Стиль определён в `backend/pint.json` (пресет PSR-12). Статический анализ — PHPStan level 10 с Larastan (`backend/phpstan.neon`). Ключевые правила:

- `declare(strict_types=1)` в каждом PHP-файле
- Типизированные параметры, возвраты и свойства — без `mixed`, без подавления ошибок через `@phpstan-ignore`
- Архитектура — DDD: `backend/app/Domain/<Context>/` для бизнес-логики, `backend/app/Infrastructure/<Context>/` для адаптеров к внешним системам (см. раздел «Доменный и инфраструктурный слои»)
- Use Cases оформляются как `final readonly` Action-классы с одним публичным методом `__invoke()` (см. `Domain/<Context>/Actions/`)
- Внешние клиенты реализуют интерфейс из `Domain/<Context>/Services/` (например, `Bitrix24ClientInterface` → `Infrastructure/Bitrix24/Bitrix24Client`)
- Enum-ы для всех перечислимых значений (см. `Domain/<Context>/Enums/`)
- DTO через `readonly`-классы (см. `Domain/<Context>/DTOs/`)
- Value Objects — в `Domain/<Context>/ValueObjects/` или `Domain/Shared/ValueObjects/`
- Провайдеры регистрируют биндинги интерфейс → реализация

Пример Action-класса:

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

Пример инфраструктурного клиента:

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

ESLint-конфиг — `frontend/eslint.config.js`. Правила:

- TypeScript strict mode, нет `any`
- Функциональные компоненты с хуками, без классов
- API-клиенты — в `frontend/src/api/`, каждый модуль по домену (`sync`, `reports`, `inbox`, `settings`)
- Хуки — в `frontend/src/hooks/`, оборачивают TanStack Query (`useQuery` / `useMutation`)
- Типы API — в `frontend/src/types/api.ts`

## Тестирование

Backend-тесты расположены в `backend/tests/`. Тесты используют in-memory SQLite (`DB_DATABASE=:memory:`). Фикстуры JSON для моков внешних API — в `backend/tests/Fixtures/`. Мок LLM-провайдера — `backend/tests/Mocks/MockLlmProvider.php`.

При добавлении нового функционала — писать и unit-, и feature-тесты. Моки внешних сервисов (GitLab, Bitrix24, LLM) обязательны: тесты не должны делать реальных HTTP-запросов.

Раскладка тестов отражает DDD-структуру: `tests/Unit/Domain/<Context>/Actions/`, `tests/Unit/Domain/<Context>/Models/`, `tests/Unit/Domain/<Context>/Queries/`, `tests/Unit/Domain/<Context>/ValueObjects/`. Feature-тесты — в `tests/Feature/<Area>/`.

Паттерн feature-теста:

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
        // GIVEN: мок LLM-провайдера и отчёт со связанными задачами
        $this->app->bind(LlmProviderInterface::class, MockLlmProvider::class);
        $report = Report::factory()->withTasks(3)->create();

        // WHEN: запускаем генерацию нарративов через Action
        $action = $this->app->make(GenerateNarrativesForReport::class);
        $action($report);

        // THEN: нарративы записаны в историю
        $this->assertDatabaseCount('narrative_history', 3);
    }
}
```

Ключевые правила: структура GIVEN-WHEN-THEN в комментариях, `final class`, мок внешних сервисов через контейнер (LLM — через `MockLlmProvider`), фабрики для тестовых данных, фикстуры JSON для ответов внешних API — в `backend/tests/Fixtures/`.

Перед коммитом убедись, что проходят все проверки:

```bash
make lint && make test
```

## Git-воркфлоу

- Формат коммитов: [Conventional Commits](https://www.conventionalcommits.org/) — `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
- Одна логическая задача — один PR
- Перед PR: `make lint && make test` без ошибок
- Код и комментарии — на английском языке

## Архитектурные решения

### Data Flow

Основной поток данных при генерации отчёта:

1. **Синхронизация** — `RunSyncJob` (очередь Redis) выполняет actions `Domain/Sync/Actions/SyncGitLab` и `SyncBitrix24` (с подэтапами `SyncBitrix24Tasks`, `SyncBitrix24TimeEntries`), которые через клиенты `Infrastructure/GitLab/GitLabClient` и `Infrastructure/Bitrix24/Bitrix24Client` забирают данные и сохраняют в SQLite. По завершении — событие `SyncCompleted`.
2. **Сопоставление** — слушатель `MatchBranchesOnSyncCompleted` запускает actions `Domain/Matching/Actions/MatchBranch` (по одному) или `MatchAllUnmatched` (массово). Имя ветки парсится `Domain/GitLab/Services/BranchParser`, по номеру задачи находится `Bitrix24\Models\Task`, уровень уверенности — enum `ConfidenceLevel`. Несопоставленные коммиты попадают в Inbox (`Domain/Inbox/Queries/GetUnlinkedBranches`).
3. **Сборка отчёта** — action `Domain/Report/Actions/GenerateReport` компонует данные (задачи + коммиты + временные записи) в структуру отчёта и эмитит событие `ReportGenerated`.
4. **Нарратив** — слушатель `GenerateNarrativesOnReportGenerated` запускает action `Domain/Narrative/Actions/GenerateNarrativesForReport`, который через `LlmProviderInterface` (реализации в `Infrastructure/LLM/` — `LlmManager` выбирает `ClaudeProvider` / `OpenAiProvider`) получает человекочитаемые описания. Поддерживается ручное редактирование (`EditDayNarrative`, `EditTaskNarrative`) и регенерация (`Regenerate*Narrative`) с записью в `NarrativeHistory`.
5. **Экспорт** — `Infrastructure/Report/WordExporter` (реализация `Domain/Report/Services/ReportExporterInterface`, PHPWord) генерирует финальный `.docx`-файл. Пустые дни обрабатываются методом `addEmptyDayCell()`, источник дня помечается enum `ReportDaySource`.

Пользователь управляет процессом через React-фронтенд: запускает синхронизацию, проверяет сопоставления в Inbox, корректирует связи, генерирует и скачивает отчёт.

### Доменный и инфраструктурный слои

Backend организован по DDD-принципу: бизнес-логика — в `backend/app/Domain/<Context>/`, адаптеры к внешним системам — в `backend/app/Infrastructure/<Context>/`.

**`backend/app/Domain/`** — 9 ограниченных контекстов, у каждого своя структура (`Actions/`, `DTOs/`, `Enums/`, `Events/`, `Listeners/`, `Models/`, `Queries/`, `Services/` (контракты), `ValueObjects/` — выборочно):

- `GitLab/` — модели `Branch`, `Commit`; парсеры `BranchParser`, `ConventionalCommitParser`; контракт `GitLabClientInterface`; DTO `ParsedBranch`
- `Bitrix24/` — модели `Task`, `TimeEntry`; контракт `Bitrix24ClientInterface`; enum-ы `TaskStatus`, `ParticipationRole`
- `Matching/` — actions `MatchBranch`, `MatchAllUnmatched`, `RematchBranch`; модель `MatchResult`; enum `ConfidenceLevel`; событие `BranchMatched` + слушатель `MatchBranchesOnSyncCompleted`
- `Narrative/` — actions `GenerateNarrativesForReport`, `EditDay/TaskNarrative`, `RegenerateDay/TaskNarrative`, `UndoDay/TaskNarrative`; модель `NarrativeHistory`; контракт `LlmProviderInterface`; служебный `NarrativeSupport`
- `Report/` — action `GenerateReport`; модели `Report`, `ReportDay`, `ReportTask`, `ReportDayTask`; queries `GetReportPreview`, `GetMonthlyReportData`, `GetTaskTimeBreakdown` и др.; контракты `ReportExporterInterface`, `PromptExportServiceInterface`; enum-ы `ReportType`, `ReportStatus`, `ReportDaySource`; ValueObject `Narrative`
- `Sync/` — actions `SyncGitLab`, `SyncBitrix24` (+ `SyncBitrix24Tasks`, `SyncBitrix24TimeEntries`, `SyncBitrix24ForReport`, `EnsureTasksForPeriod`); модели `SyncJob`, `SyncLog`; событие `SyncCompleted`
- `Inbox/` — actions `AssignBranch`, `BulkAssignBranches`, `CreateTaskAndAssign`, `IgnoreBranch`; query `GetUnlinkedBranches`
- `Settings/` — модель `Setting`
- `Shared/` — переиспользуемые ValueObjects `DateRange`, `TaskNumber`

**`backend/app/Infrastructure/`** — реализации контрактов из доменов и адаптеры к внешним API:

- `Bitrix24/Bitrix24Client.php` — реализация `Domain\Bitrix24\Services\Bitrix24ClientInterface`
- `GitLab/GitLabClient.php` — реализация `Domain\GitLab\Services\GitLabClientInterface`
- `LLM/` — `LlmManager` (диспетчер провайдеров), `ClaudeProvider`, `OpenAiProvider` (все реализуют `Domain\Narrative\Services\LlmProviderInterface`)
- `Report/` — `WordExporter`, `PromptExportService` (реализации одноимённых интерфейсов из `Domain/Report/Services/`)

**`backend/app/Models/`** — только `User.php`. Все доменные модели лежат в `Domain/<Context>/Models/`.

**`backend/app/Http/Controllers/`** — тонкий слой (`InboxController`, `ReportController`, `SettingsController`, `SyncController`): принять запрос → вызвать Action/Query → вернуть API Resource.

Фоновая синхронизация выполняется через `App\Jobs\RunSyncJob` (Laravel Queue, Redis).

## Границы

- Никогда не коммить секреты, токены и API-ключи. Все секреты — только через `.env`-файлы, которые в `.gitignore`
- Не модифицировать файлы в `backend/vendor/` и `frontend/node_modules/`
- Не менять конфигурацию PHPStan level и Pint-пресет без явного согласования
- Не отключать и не ослаблять правила ESLint / TypeScript strict
- Не добавлять зависимости без обоснования — минимум внешних пакетов
- Не делать реальных HTTP-вызовов в тестах — только моки
- Не удалять и не игнорировать падающие тесты — чинить