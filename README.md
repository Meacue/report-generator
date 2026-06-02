# Report Generator
I'm a developer, not a report writer — I'd rather not spend time writing reports, but I'm happy to spend it programming. That's how this project was born.

A web application for automatic generation of developer reports. It synchronizes data from GitLab and Bitrix24, links MR commits to Bitrix tasks, generates narrative descriptions of the work done — based on commits, in Russian, via an LLM — and exports the result to `.docx`.

## Features

- **Data synchronization** — automatic import of commits from GitLab and tasks from Bitrix24
- **Matching** — links commits to tasks by branches and naming patterns (the branch name must contain the Bitrix task ID; that's how the Bitrix → GitLab match is performed)
- **Narrative generation** — an LLM (Claude or OpenAI) produces business-style descriptions of the work performed in Russian, based on commits. The style and other formatting details can be configured via a system prompt editable on the settings page.
- **Word export** — a ready-to-use `.docx` report for the selected period
- **Web interface** — a React SPA for managing reports and settings

## Requirements

- Docker and Docker Compose
- A GitLab token (Personal Access Token with `read_api` scope)
- A Bitrix24 webhook (REST API)
- An LLM provider API key (Anthropic Claude or OpenAI)

## Quick start

1. **Clone the repository:**

```bash
git clone https://github.com/your-username/report-generator.git
cd report-generator
```

2. **Copy the environment files and fill in the secrets:**

```bash
cp .env.example .env
cp backend/.env.example backend/.env
```

Edit `backend/.env` — provide GitLab, Bitrix24 and LLM provider tokens (see the "Configuration" section).

3. **Start the project:**

```bash
make build
make up
make migrate
```

4. **Open in your browser:**

- Frontend: [http://localhost:5173](http://localhost:5173)
- Backend API: [http://localhost:8000](http://localhost:8000)

## Configuration

All settings are provided via environment variables in `backend/.env`:

### GitLab

| Variable | Description |
|---|---|
| `GITLAB_URL` | URL of your GitLab instance (default `https://gitlab.com`) |
| `GITLAB_TOKEN` | Personal Access Token with `read_api` scope |
| `GITLAB_USERNAME` | Your GitLab username |

### Bitrix24

| Variable | Description |
|---|---|
| `BITRIX24_URL` | URL of your Bitrix24 REST API (e.g. `https://company.bitrix24.ru/rest/ID/`) |
| `BITRIX24_USER_ID` | User ID in Bitrix24 |
| `BITRIX24_API_KEY` | Bitrix24 webhook key |

### LLM

| Variable | Description |
|---|---|
| `LLM_PROVIDER` | Provider: `claude` or `openai` (default `claude`) |
| `LLM_API_KEY` | API key for the selected provider |
| `LLM_CLAUDE_MODEL` | Claude model (default `claude-sonnet-4-20250514`) |
| `LLM_OPENAI_MODEL` | OpenAI model (default `gpt-4o-mini`) |
| `LLM_MAX_TOKENS` | Maximum tokens in the response (default `1024`) |

## Common commands

```bash
make up          # Start containers
make down        # Stop containers
make build       # Rebuild images
make migrate     # Apply migrations
make test        # Run tests (PHPUnit)
make lint        # Lint the code (Pint, PHPStan, ESLint, Prettier, hadolint)
make fix         # Auto-format
make shell       # Shell inside the app container
make logs        # Container logs
```

## Technology stack

- **Backend:** PHP 8.2+ / Laravel 12 (DDD: `app/Domain/` + `app/Infrastructure/`)
- **Frontend:** React 18 (TypeScript) / Vite
- **Database:** SQLite
- **Queues:** Redis + Laravel Queue
- **Word generation:** PHPWord
- **LLM:** Anthropic Claude API / OpenAI API
- **Containerization:** Docker Compose

For more on the code structure, see [AGENTS.md](AGENTS.md) (the "Domain and Infrastructure layers" section). Backend specifics live in [backend/README.md](backend/README.md), frontend specifics in [frontend/README.md](frontend/README.md).

## Contributing

Feedback and contributions are very welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before sending a pull request.

## License

The project is distributed under the [MIT](LICENSE) license.
