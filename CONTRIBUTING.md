# Contributing

Contributions to Report Generator are very welcome. Below are the rules for working with the repository.

## Getting started

1. Fork the repository.
2. Clone your fork, install dependencies and git hooks:

```bash
git clone https://github.com/your-username/report-generator.git
cd report-generator
npm install              # installs lefthook + commitlint
npx lefthook install     # activates git hooks locally
```

3. Set up the project (see "Quick start" in [README.md](README.md)).

## Issues — the entry point for every task

Every change starts with a GitHub Issue. This applies to bugs, features, documentation tweaks and dependency updates alike. The "Either Bugs or Pull Requests" principle — every change must be linked to an issue — keeps:

- the `issue → branch → commit → PR → release → CHANGELOG` chain traceable;
- release-please able to auto-link releases to issues;
- the project history self-documenting.

Issue templates live in [.github/ISSUE_TEMPLATE/](.github/ISSUE_TEMPLATE/).

## Branch naming — Conventional Branch

**Format — five accepted forms:**

| Form | When to use | Example |
|---|---|---|
| `<type>/<slug>` | task without an issue | `chore/update-deps` |
| `<type>/<issue#>` | issue exists, slug is redundant | `feat/4` |
| `<type>/#<issue#>` | same, GitHub-style with `#` | `feat/#4` |
| `<type>/<issue#>-<slug>` | issue + readable name | `feat/9-conventional-branches` |
| `<type>/#<issue#>-<slug>` | same with `#` | `feat/#9-conventional-branches` |
| `release/<MAJOR>.<MINOR>.<PATCH>` | release preparation (dots are allowed only here) | `release/0.2.0`, `release/1.0.0-rc1` |

The slug is optional — if the issue describes the task well enough, just use the number. The `#` prefix is optional and has no effect (purely a visual sync with GitHub).

**Allowed types:**

| Type | Purpose |
|---|---|
| `feat/` | new functionality |
| `fix/` | bug fix |
| `hotfix/` | urgent prod patch |
| `chore/` | housekeeping (dependencies, configs) |
| `docs/` | documentation |
| `refactor/` | refactor with no behavior change |
| `perf/` | optimization |
| `test/` | tests |
| `ci/` | CI configuration |
| `build/` | build / Docker |
| `style/` | formatting |
| `security/` | security patches |
| `deps/` | dependency updates |
| `release/` | release preparation |

**Name rules:** only `a-z`, `0-9`, `-` and an optional `#` before the issue number. Lowercase. No more than ~50 characters. No `_`, spaces, or emoji; no leading, trailing, or doubled dashes; no dots except in `release/`.

**Examples:**
- `feat/4` — minimal form with an issue
- `feat/#4` — same with the GitHub-style prefix
- `feat/9-conventional-branches` — feature for issue #9
- `fix/12-bitrix-comments-encoding` — bug for issue #12
- `chore/update-deps` — without an issue
- `release/0.2.0` — release PR

The branch name is checked automatically in the pre-commit hook (lefthook). Details — in [lefthook.yml](lefthook.yml).

## Commit messages — Conventional Commits

Spec: [conventionalcommits.org/v1.0.0](https://www.conventionalcommits.org/en/v1.0.0/).

**Format:**

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

**Types and CHANGELOG mapping:**

| Type | Purpose | CHANGELOG section | Bump |
|---|---|---|---|
| `feat` | new functionality | `Added` | MINOR (after 1.0.0) |
| `fix` | bug fix | `Fixed` | PATCH |
| `perf` | optimization | `Changed` | PATCH |
| `refactor` | refactor | `Changed` | PATCH |
| `revert` | commit revert | `Removed` | PATCH |
| `deps` | dependency updates | `Changed` | PATCH |
| `security` | security patch | `Security` | PATCH |
| `docs` | documentation | hidden | — |
| `chore` | housekeeping | hidden | — |
| `test` | tests | hidden | — |
| `ci` | CI configuration | hidden | — |
| `build` | build / Docker | hidden | — |
| `style` | formatting | hidden | — |

**`BREAKING CHANGE`** — declared in the footer or with an exclamation mark after the type: `feat!:`, `fix!:`. Bumps MAJOR (only after 1.0.0; before 1.0.0 — per [SemVer §4](https://semver.org/#spec-item-4) — it does not have to).

**Linking to issues** — in the footer: `Refs #23` (mention) or `Closes #23` (auto-close on merge).

**Examples:**

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

The commit message is checked automatically in the commit-msg hook (lefthook).

## Code standards

### Backend (PHP)

- PHP 8.2+, Laravel 12, strict typing (`declare(strict_types=1)`).
- Architecture — DDD: business logic in `backend/app/Domain/<Context>/`, adapters to external systems in `backend/app/Infrastructure/<Context>/` (see [AGENTS.md](AGENTS.md)).
- Use Cases — `final readonly` Action classes in `Domain/<Context>/Actions/` with a single public `__invoke()` method.
- External clients implement an interface from `Domain/<Context>/Services/` (e.g., `Bitrix24ClientInterface` → `Infrastructure/Bitrix24/Bitrix24Client`).
- Code style — PSR-12, enforced via [Laravel Pint](https://laravel.com/docs/pint).
- Static analysis — PHPStan level 10 + Larastan.
- English in code and comments.

### Frontend (TypeScript)

- TypeScript strict mode, functional components.
- ESLint + Prettier for linting and formatting.

### Docker

- Dockerfiles (`.docker/*/Dockerfile`) are linted with [hadolint](https://github.com/hadolint/hadolint).
- `DL3018` (pin apk versions) is intentionally disabled — see `.hadolint.yaml` for the rationale.

### Pre-commit checks

```bash
make lint   # check without auto-fix
make fix    # auto-format
make test   # run tests
```

The linter runs automatically in the pre-commit hook. If the hook didn't fire (e.g., lefthook isn't installed), CI will reject your changes.

## Pull Request

1. PRs are based on `master`.
2. One PR — one logical task (one issue).
3. The PR title must follow Conventional Commits (it becomes the single-commit message after squash-merge).
4. Reference the issue in the description: `Closes #N` or `Refs #N`.
5. Make sure `make lint` and `make test` pass without errors.
6. Describe *what* and *why*, not *how* — *how* is visible in the diff.

PR branches are merged via **squash & merge**: each branch becomes one commit on `master`. This keeps history flat and lets release-please see a clean stream of conventional commits.

## Dependency management

Dependencies are updated by [Dependabot](https://docs.github.com/en/code-security/dependabot) — built into GitHub. Configuration lives in [`.github/dependabot.yml`](.github/dependabot.yml).

**Two independent mechanisms:**

1. **Security updates** — opened automatically when a CVE is detected in dependencies.
2. **Version updates** — weekly updates of dependencies to the latest versions. Configured in `dependabot.yml`.

**What's monitored:**

- `composer` in `/backend` — weekly
- `npm` in `/frontend` — weekly
- `npm` in `/` (root tooling: lefthook + commitlint) — monthly
- `github-actions` in `/` — monthly

**Package groups** (one PR per group instead of one per package): laravel + illuminate, react-stack, eslint-stack, php-testing, dev-deps.

**Conventional Commits:** Dependabot commits as `deps(scope): bump foo from 1.2.0 to 1.2.3` — fits our `commitlint` schema (`deps:` is in the type enum) and release-please maps it to the `Changed` CHANGELOG section.

## Releases

Releases are managed automatically by [release-please](https://github.com/googleapis/release-please):

1. After a PR is merged into `master`, the bot scans for new conventional commits since the last tag.
2. The bot opens (or updates) a **release PR** titled `chore: release X.Y.Z`. That PR contains:
   - a version bump in [`.release-please-manifest.json`](.release-please-manifest.json),
   - an updated [`CHANGELOG.md`](CHANGELOG.md) in [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format — `Added` / `Changed` / `Fixed` / `Security` / `Removed` sections (type mapping is defined in [`release-please-config.json`](release-please-config.json)).
3. When you merge the release PR, the bot:
   - creates a `X.Y.Z` git tag (no `v` prefix),
   - publishes a GitHub Release with auto-generated release notes.

**When to move to `1.0.0`** — that's a developer decision, not the bot's. Before 1.0.0 the project is in the SemVer "everything may change without BC warnings" state, so `BREAKING CHANGE` is not required to bump MAJOR.

## Bug reports

Open a [Bug Report](.github/ISSUE_TEMPLATE/bug_report.yml) — the template will ask for everything that's needed.

## Feature requests

Open a [Feature Request](.github/ISSUE_TEMPLATE/feature_request.yml) — the template will ask for the problem, the desired behavior and alternatives.

## Code of conduct

By participating in the project you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).
