# Frontend — Report Generator

React SPA для генерации отчётов разработчика. Обращается к backend (Laravel 12) через REST API, получает статус синхронизации через Server-Sent Events.

Главный документ проекта — [../AGENTS.md](../AGENTS.md). Здесь — только то, что специфично для `frontend/`.

## Стек

Версии зафиксированы в [`package.json`](package.json).

| Технология | Версия |
| --- | --- |
| React | 18.3 |
| TypeScript | 5.6 |
| Vite | 5.4 |
| React Router DOM | 7.13 |
| TanStack Query | 5.90 |
| ESLint | 9.13 (`typescript-eslint` 8.11) |
| Prettier | конфиг общий с проектом |

## Структура `src/`

```
src/
  api/          модули по доменам — по одному файлу на ресурс
  hooks/        обёртки над TanStack Query
  pages/        точки входа маршрутов
  components/   переиспользуемые блоки, сгруппированные по доменам
  types/        TypeScript-типы запросов и ответов API
  utils/        вспомогательные функции
```

- **`api/`** — `client.ts` обёртка над `fetch` (базовый URL, заголовки, обработка ошибок); `sync.ts`, `reports.ts`, `inbox.ts`, `settings.ts` — по одному файлу на ресурс backend.
- **`hooks/`** — `useReports`, `useInbox`, `useSync` поверх `useQuery` / `useMutation`; `useSyncSSE` — Server-Sent Events для статуса синхронизации в реальном времени.
- **`pages/`** — `DashboardPage`, `InboxPage`, `ReportPage`, `SettingsPage`. Маршруты подключаются в `App.tsx`.
- **`components/`** — подпапки по доменам: `inbox/`, `report/`, `sync/`, `layout/` (`AppLayout` — общий каркас страниц).
- **`types/`** — `api.ts` с типами DTO, общими для нескольких модулей.
- **`utils/`** — `formatDuration`, `taskTitle` и прочие чистые функции.

## Запуск

Установка и запуск выполняются через корневой `Makefile`, см. [../README.md](../README.md). Frontend поднимается контейнером `node` командой `make up` и слушает `http://localhost:5173`. Vite проксирует API-запросы на backend (`http://localhost:8000`).

Локальные команды (npm-скрипты в `package.json`) выполняются внутри контейнера `node`. Точные команды `docker compose exec node …` см. в корневом [AGENTS.md](../AGENTS.md).

## Стиль кода

- TypeScript strict mode (`tsconfig.app.json`).
- ESLint — конфиг [`eslint.config.js`](eslint.config.js): `typescript-eslint`, `react-hooks`, `react-refresh`.
- Prettier — общие настройки проекта.
- Только функциональные компоненты и хуки, без классов.
- `any` запрещён; неизвестные данные типизируем через `unknown` и сужаем.

Проверки и автоисправление запускаются из корня для всего репозитория:

```bash
make lint   # backend + frontend (Pint, PHPStan, ESLint, Prettier)
make fix    # автоисправление (Pint + Prettier)
```

Подробнее о правилах и архитектуре — [../AGENTS.md](../AGENTS.md).

## См. также

- [../AGENTS.md](../AGENTS.md) — главный документ: стек, команды, архитектура, правила code review.
- [../README.md](../README.md) — пользовательская часть README.
- [../CONTRIBUTING.md](../CONTRIBUTING.md) — процесс контрибьюции.
