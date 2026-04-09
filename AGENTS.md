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
- Каждый сервис реализует интерфейс (например `SyncServiceInterface` → `SyncService`)
- Enum-ы для всех перечислимых значений (см. `backend/app/Enums/`)
- DTO через readonly-классы (см. `backend/app/DTOs/`)
- Провайдеры регистрируют биндинги интерфейс → реализация

Пример правильного сервиса:

```php
<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Services\Matching\MatchingEngineInterface;

final class MatchingEngine implements MatchingEngineInterface
{
    public function match(Branch $branch): MatchResult
    {
        // ...
    }
}
```

### Frontend (TypeScript)

ESLint-конфиг — `frontend/eslint.config.js`. Правила:

- TypeScript strict mode, нет `any`
- Функциональные компоненты с хуками, без классов
- API-клиенты — в `frontend/src/api/`, каждый модуль по домену (sync, reports, inbox, mappings, settings)
- Хуки — в `frontend/src/hooks/`, оборачивают TanStack Query (`useQuery` / `useMutation`)
- Типы API — в `frontend/src/types/api.ts`

## Тестирование

Backend-тесты расположены в `backend/tests/`. Тесты используют in-memory SQLite (`DB_DATABASE=:memory:`). Фикстуры JSON для моков внешних API — в `backend/tests/Fixtures/`. Мок LLM-провайдера — `backend/tests/Mocks/MockLlmProvider.php`.

При добавлении нового функционала — писать и unit-, и feature-тесты. Моки внешних сервисов (GitLab, Bitrix24, LLM) обязательны: тесты не должны делать реальных HTTP-запросов.

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

Сервисный слой организован по доменам в `backend/app/Services/`:

- `GitLab/` — клиент GitLab API, парсер веток
- `Bitrix24/` — клиент Bitrix24 REST API
- `LLM/` — Strategy-паттерн: `LlmManager` выбирает провайдера (`ClaudeProvider` / `OpenAiProvider`) через `LlmProviderInterface`
- `Matching/` — движок сопоставления коммитов с задачами
- `Narrative/` — генерация нарративных описаний через LLM
- `Report/` — сборка отчёта (`ReportBuilder`), экспорт в Word (`WordExporter`), экспорт промпта
- `Sync/` — оркестрация синхронизации данных, парсер conventional commits
- `Inbox/` — управление нераспределёнными коммитами

Фоновая синхронизация выполняется через `RunSyncJob` (Laravel Queue, Redis).

## Границы

- Никогда не коммить секреты, токены и API-ключи. Все секреты — только через `.env`-файлы, которые в `.gitignore`
- Не модифицировать файлы в `backend/vendor/` и `frontend/node_modules/`
- Не менять конфигурацию PHPStan level и Pint-пресет без явного согласования
- Не отключать и не ослаблять правила ESLint / TypeScript strict
- Не добавлять зависимости без обоснования — минимум внешних пакетов
- Не делать реальных HTTP-вызовов в тестах — только моки
- Не удалять и не игнорировать падающие тесты — чинить
- `private-docs/` содержит внутреннюю документацию (PRD, ADR, архитектура) — не публиковать, не ссылаться в публичном коде
