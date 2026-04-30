# Участие в разработке

Рад приветствовать ваш вклад в Report Generator. Ниже — правила работы с репозиторием.

## Начало работы

1. Сделайте форк репозитория.
2. Клонируйте форк, установите зависимости и git-хуки:

```bash
git clone https://github.com/your-username/report-generator.git
cd report-generator
npm install              # ставит lefthook + commitlint
npx lefthook install     # активирует git-хуки локально
```

3. Разверните проект (см. «Быстрый старт» в [README.md](README.md)).

## Issues — точка входа для любой задачи

Любое изменение начинается с GitHub Issue. Это касается и багов, и фич, и правок документации, и обновлений зависимостей. Принцип «Either Bugs or Pull Requests» — каждое изменение должно быть связано с issue, чтобы:

- цепочка `issue → branch → commit → PR → release → CHANGELOG` оставалась трассируемой;
- release-please мог автоматически линковать релиз с issues;
- история проекта была самодокументируемой.

Шаблоны issues в [.github/ISSUE_TEMPLATE/](.github/ISSUE_TEMPLATE/).

## Именование веток — Conventional Branch

**Формат — пять допустимых форм:**

| Форма | Когда использовать | Пример |
|---|---|---|
| `<type>/<slug>` | задача без issue | `chore/update-deps` |
| `<type>/<issue#>` | есть issue, slug избыточен | `feat/4` |
| `<type>/#<issue#>` | то же, GitHub-стиль с `#` | `feat/#4` |
| `<type>/<issue#>-<slug>` | issue + читаемое имя | `feat/9-conventional-branches` |
| `<type>/#<issue#>-<slug>` | то же с `#` | `feat/#9-conventional-branches` |
| `release/<MAJOR>.<MINOR>.<PATCH>` | подготовка релиза (точки разрешены только тут) | `release/0.2.0`, `release/1.0.0-rc1` |

Slug опционален — если issue достаточно описывает задачу, ограничьтесь номером. Префикс `#` опционален и не влияет ни на что (визуальная синхронизация с GitHub).

**Допустимые типы:**

| Тип | Назначение |
|---|---|
| `feat/` | новая функциональность |
| `fix/` | исправление бага |
| `hotfix/` | срочный прод-патч |
| `chore/` | служебные задачи (зависимости, конфиги) |
| `docs/` | документация |
| `refactor/` | рефакторинг без изменения поведения |
| `perf/` | оптимизация |
| `test/` | тесты |
| `ci/` | конфигурация CI |
| `build/` | сборка/Docker |
| `style/` | форматирование |
| `security/` | патчи безопасности |
| `deps/` | обновление зависимостей |
| `release/` | подготовка релиза |

**Правила имени:** только `a-z`, `0-9`, `-` и опциональный `#` перед issue-номером. Lowercase. Не более ~50 символов. Никаких `_`, пробелов, эмодзи; никаких ведущих/висящих/двойных дефисов; никаких точек кроме `release/`.

**Примеры:**
- `feat/4` — минимальная форма с issue
- `feat/#4` — то же с GitHub-префиксом
- `feat/9-conventional-branches` — фича по issue #9
- `fix/12-bitrix-comments-encoding` — баг по issue #12
- `chore/update-deps` — без issue
- `release/0.2.0` — release-PR

Имя ветки проверяется автоматически в pre-commit хуке (lefthook). Подробности — в [lefthook.yml](lefthook.yml).

## Сообщения коммитов — Conventional Commits

Спека: [conventionalcommits.org/v1.0.0](https://www.conventionalcommits.org/en/v1.0.0/).

**Формат:**

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

**Типы и связь с CHANGELOG:**

| Тип | Назначение | Секция CHANGELOG | Bump |
|---|---|---|---|
| `feat` | новая функциональность | `Added` | MINOR (после 1.0.0) |
| `fix` | исправление бага | `Fixed` | PATCH |
| `perf` | оптимизация | `Changed` | PATCH |
| `refactor` | рефакторинг | `Changed` | PATCH |
| `revert` | откат коммита | `Removed` | PATCH |
| `deps` | обновление зависимостей | `Changed` | PATCH |
| `security` | патч безопасности | `Security` | PATCH |
| `docs` | документация | скрыто | — |
| `chore` | служебные задачи | скрыто | — |
| `test` | тесты | скрыто | — |
| `ci` | конфигурация CI | скрыто | — |
| `build` | сборка/Docker | скрыто | — |
| `style` | форматирование | скрыто | — |

**`BREAKING CHANGE`** — указывается в footer или восклицательным знаком после типа: `feat!:`, `fix!:`. Бампит MAJOR (только после 1.0.0; до 1.0.0 — согласно [SemVer §4](https://semver.org/#spec-item-4) — не обязан).

**Связь с issue** — в footer: `Refs #23` (упоминание) или `Closes #23` (auto-close при мердже).

**Примеры:**

```
feat(bitrix): sanitize PII in comment payload

Refs #23
```

```
fix(report): correct LLM token counter for Russian text

Closes #14
```

```
feat!: redesign /api/reports response shape

BREAKING CHANGE: response wrapper renamed from `data` to `result`.
Refs #28
```

Сообщение коммита проверяется автоматически в commit-msg хуке (lefthook).

## Стандарты кода

### Backend (PHP)

- PHP 8.2+, Laravel 12, строгая типизация (`declare(strict_types=1)`).
- Архитектура — DDD: бизнес-логика в `backend/app/Domain/<Context>/`, адаптеры к внешним системам в `backend/app/Infrastructure/<Context>/` (см. [AGENTS.md](AGENTS.md)).
- Use Cases — `final readonly` Action-классы в `Domain/<Context>/Actions/` с одним публичным методом `__invoke()`.
- Внешние клиенты реализуют интерфейс из `Domain/<Context>/Services/` (например, `Bitrix24ClientInterface` → `Infrastructure/Bitrix24/Bitrix24Client`).
- Стиль кода — PSR-12, проверяется через [Laravel Pint](https://laravel.com/docs/pint).
- Статический анализ — PHPStan level 10 + Larastan.
- Английский язык в коде и комментариях.

### Frontend (TypeScript)

- TypeScript strict mode, функциональные компоненты.
- ESLint + Prettier для проверки и форматирования.

### Проверка перед коммитом

```bash
make lint   # проверка без исправлений
make fix    # автоисправление форматирования
make test   # запуск тестов
```

Линтер запускается автоматически в pre-commit хуке. Если хук не сработал (например, lefthook не установлен) — ваши изменения отвергнет CI.

## Pull Request

1. PR базируется на `master`.
2. Один PR — одна логическая задача (один issue).
3. Заголовок PR — в формате Conventional Commits (он же будет single-commit-сообщением при squash-merge).
4. В описании сошлитесь на issue: `Closes #N` или `Refs #N`.
5. Убедитесь, что `make lint` и `make test` проходят без ошибок.
6. Описывайте *что* и *зачем*, а не *как* — *как* видно по diff'у.

PR-ветки мерджатся через **squash & merge**: каждая ветка → один коммит на `master`. Это держит историю плоской и release-please видит чистый поток conventional commits.

## Релизы

Релизами управляет [release-please](https://github.com/googleapis/release-please) автоматически:

1. После мерджа PR в `master` бот сканирует новые conventional commits с прошлого тега.
2. Бот открывает (или обновляет) **release-PR** с заголовком `chore: release X.Y.Z`. В этом PR:
   - bump версии в [`.release-please-manifest.json`](.release-please-manifest.json),
   - обновлённый [`CHANGELOG.md`](CHANGELOG.md) в формате [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) — секции `Added` / `Changed` / `Fixed` / `Security` / `Removed` (маппинг типов задан в [`release-please-config.json`](release-please-config.json)).
3. Когда вы мерджите release-PR — бот:
   - создаёт git-тег `X.Y.Z` (без префикса `v`),
   - публикует GitHub Release с автогенерируемыми release notes.

**Когда переходить на `1.0.0`** — это решение разработчика, а не бота. До 1.0.0 проект в SemVer-смысле «всё может меняться без BC-предупреждений», поэтому `BREAKING CHANGE` не обязан бампить MAJOR.

## Сообщения об ошибках

Создайте [Bug Report](.github/ISSUE_TEMPLATE/bug_report.yml) — шаблон попросит указать всё необходимое.

## Предложения новых возможностей

Создайте [Feature Request](.github/ISSUE_TEMPLATE/feature_request.yml) — шаблон попросит указать проблему, желаемое поведение и альтернативы.

## Кодекс поведения

Участвуя в проекте, вы соглашаетесь соблюдать [Кодекс поведения](CODE_OF_CONDUCT.md).
