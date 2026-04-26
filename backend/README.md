# Backend — Report Generator

Backend Laravel-приложения Report Generator. Бизнес-логика организована по DDD: `Domain/` (контексты) + `Infrastructure/` (адаптеры внешних сервисов).

Полный разбор архитектуры — в корневом [AGENTS.md](../AGENTS.md). Установка и запуск окружения — в корневом [README.md](../README.md).

## Стек

- PHP `^8.2`, расширение `ext-redis`
- Laravel `^12.0`, `laravel/tinker` `^2.10.1`
- PHPWord `^1.4` (генерация `.docx`)
- PHPUnit `^11.5.50` (тесты на in-memory SQLite)
- Larastan `^3.9` (PHPStan level 10), Laravel Pint `^1.29`
- Mockery `^1.6`, FakerPHP `^1.23`, Collision `^8.6`, Pail `^1.2.2`, Sail `^1.41`

Версии — из [composer.json](composer.json).

## Структура `app/`

```
app/
├── Domain/<Context>/{Actions, DTOs, Enums, Events, Listeners, Models, Queries, Services, ValueObjects}
├── Infrastructure/{Bitrix24, GitLab, LLM, Report}
├── Http/Controllers/
├── Jobs/
├── Console/Commands/
└── Models/User.php
```

**Контексты (`Domain/`):** `Bitrix24`, `GitLab`, `Inbox`, `Matching`, `Narrative`, `Report`, `Settings`, `Shared`, `Sync`. Подпапки внутри контекста создаются по необходимости — не каждый контекст содержит все девять.

**Инфраструктура (`Infrastructure/`):**

- `Bitrix24/Bitrix24Client` — HTTP-клиент Битрикс24
- `GitLab/GitLabClient` — HTTP-клиент GitLab
- `LLM/{LlmManager, ClaudeProvider, OpenAiProvider}` — провайдеры LLM
- `Report/{WordExporter, PromptExportService}` — экспорт `.docx` и выгрузка промптов

`App\Models\User` намеренно остаётся в `app/Models/` (требование Laravel auth). Доменные модели живут в `Domain/<Context>/Models/`.

Подробное описание границ контекстов, потоков данных и принятых паттернов — в [AGENTS.md](../AGENTS.md).

## Запуск

Установка, поднятие docker-окружения и фронтенда — через корневой [Makefile](../Makefile). См. корневой [README.md](../README.md).

Команды, специфичные для backend:

- `make migrate` — применить миграции
- `make test` — PHPUnit (in-memory SQLite)
- `make lint` — Pint + PHPStan (level 10)
- `make fix` — автоисправления Pint
- Запуск конкретного теста: `docker exec moronocracy-backend php artisan test --filter=ИмяТеста`

## Тестирование

- `tests/Unit/Domain/<Context>/...` — зеркало DDD-структуры; покрывает Actions, Services, ValueObjects, Queries
- `tests/Feature/...` — HTTP/интеграционные тесты контроллеров и джобов
- In-memory SQLite (см. `phpunit.xml`)
- `tests/Mocks/MockLlmProvider.php` — детерминированный мок LLM
- `tests/Fixtures/` — фикстуры запросов/ответов внешних API

Примеры тестов и принятые соглашения — в [AGENTS.md](../AGENTS.md).

## См. также

- [AGENTS.md](../AGENTS.md) — архитектура, контексты, паттерны, тестирование
- [README.md](../README.md) — установка, запуск, команды Makefile
- [CONTRIBUTING.md](../CONTRIBUTING.md) — процесс контрибьюшна
