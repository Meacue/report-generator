# Frontend — Report Generator

A React SPA for generating developer reports. It talks to the backend (Laravel 12) over a REST API and receives sync status through Server-Sent Events.

The main project document is [../AGENTS.md](../AGENTS.md). This file covers only what's specific to `frontend/`.

## Stack

Versions are pinned in [`package.json`](package.json).

| Technology | Version |
| --- | --- |
| React | 18.3 |
| TypeScript | 5.6 |
| Vite | 5.4 |
| React Router DOM | 7.13 |
| TanStack Query | 5.90 |
| ESLint | 9.13 (`typescript-eslint` 8.11) |
| Prettier | shared project config |

## Layout of `src/`

```
src/
  api/          modules per domain — one file per resource
  hooks/        TanStack Query wrappers
  pages/        route entry points
  components/   reusable blocks, grouped by domain
  types/        TypeScript types for API requests and responses
  utils/        helper functions
```

- **`api/`** — `client.ts` is a thin wrapper over `fetch` (base URL, headers, error handling); `sync.ts`, `reports.ts`, `inbox.ts`, `settings.ts` — one file per backend resource.
- **`hooks/`** — `useReports`, `useInbox`, `useSync` on top of `useQuery` / `useMutation`; `useSyncSSE` — Server-Sent Events for real-time sync status.
- **`pages/`** — `DashboardPage`, `InboxPage`, `ReportPage`, `SettingsPage`. Routes are wired up in `App.tsx`.
- **`components/`** — subfolders by domain: `inbox/`, `report/`, `sync/`, `layout/` (`AppLayout` — the shared page chrome).
- **`types/`** — `api.ts` with DTO types shared across modules.
- **`utils/`** — `formatDuration`, `taskTitle` and other pure functions.

## Running

Install and run via the root `Makefile` — see [../README.md](../README.md). The frontend is brought up by `make up` in the `node` container and listens on `http://localhost:5173`. Vite proxies API requests to the backend (`http://localhost:8000`).

Local commands (the npm scripts in `package.json`) run inside the `node` container. See the root [../AGENTS.md](../AGENTS.md) for the exact `docker compose exec node …` commands.

## Code style

- TypeScript strict mode (`tsconfig.app.json`).
- ESLint — config [`eslint.config.js`](eslint.config.js): `typescript-eslint`, `react-hooks`, `react-refresh`.
- Prettier — shared project settings.
- Functional components and hooks only, no classes.
- `any` is forbidden; unknown data is typed as `unknown` and narrowed.

Linting and auto-fix run from the repo root for the whole repo:

```bash
make lint   # backend + frontend (Pint, PHPStan, ESLint, Prettier, hadolint)
make fix    # auto-fix (Pint + Prettier)
```

More on the rules and architecture — [../AGENTS.md](../AGENTS.md).

## See also

- [../AGENTS.md](../AGENTS.md) — main document: stack, commands, architecture, code-review rules.
- [../README.md](../README.md) — the user-facing README.
- [../CONTRIBUTING.md](../CONTRIBUTING.md) — the contribution process.
