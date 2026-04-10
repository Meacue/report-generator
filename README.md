# Report Generator
Я разработичк, а не писатель отчетов. Мне не охота тратить время на написание отчета, зато я рад заниматься программированием. Так и появился этот проект.
Веб-приложение для автоматической генерации отчётов разработчика. Синхронизирует данные из GitLab и Bitrix24, связывает коммиты с задачами, генерирует нарративные описания на русском языке с помощью LLM и экспортирует результат в `.docx`.

## Возможности

- **Синхронизация данных** — автоматический импорт коммитов из GitLab и задач из Bitrix24
- **Сопоставление** — связывание коммитов с задачами по веткам и паттернам именования
- **Генерация нарративов** — LLM (Claude или OpenAI) создаёт описания выполненной работы на русском языке в деловом стиле
- **Экспорт в Word** — готовый `.docx`-отчёт за выбранный период
- **Веб-интерфейс** — React SPA для управления отчётами и настройками

## Требования

- Docker и Docker Compose
- Токен GitLab (Personal Access Token с правами `read_api`)
- Вебхук Bitrix24 (REST API)
- API-ключ LLM-провайдера (Anthropic Claude или OpenAI)

## Быстрый старт

1. **Клонируйте репозиторий:**

```bash
git clone https://github.com/your-username/report-generator.git
cd report-generator
45```
2. **Скопируйте файлы окружения и заполните секреты:**

```bash
cp .env.example .env
cp backend/.env.example backend/.env
```

Отредактируйте `backend/.env` — укажите токены GitLab, Bitrix24 и LLM-провайдера (см. раздел «Конфигурация»).

2. **Запустите проект:**

```bash
make build
make up
make migrate
```

3. **Откройте в браузере:**

- Frontend: [http://localhost:5173](http://localhost:5173)
- Backend API: [http://localhost:8000](http://localhost:8000)

## Конфигурация

Все настройки задаются через переменные окружения в `backend/.env`:

### GitLab

| Переменная | Описание |
|---|---|
| `GITLAB_URL` | URL вашего GitLab-инстанса (по умолчанию `https://gitlab.com`) |
| `GITLAB_TOKEN` | Personal Access Token с правами `read_api` |
| `GITLAB_USERNAME` | Ваш username в GitLab |

### Bitrix24

| Переменная | Описание |
|---|---|
| `BITRIX24_URL` | URL REST API вашего Bitrix24 (например `https://company.bitrix24.ru/rest/ID/`) |
| `BITRIX24_USER_ID` | ID пользователя в Bitrix24 |
| `BITRIX24_API_KEY` | Ключ вебхука Bitrix24 |

### LLM

| Переменная | Описание |
|---|---|
| `LLM_PROVIDER` | Провайдер: `claude` или `openai` (по умолчанию `claude`) |
| `LLM_API_KEY` | API-ключ выбранного провайдера |
| `LLM_CLAUDE_MODEL` | Модель Claude (по умолчанию `claude-sonnet-4-20250514`) |
| `LLM_OPENAI_MODEL` | Модель OpenAI (по умолчанию `gpt-4o-mini`) |
| `LLM_MAX_TOKENS` | Максимум токенов в ответе (по умолчанию `1024`) |

## Основные команды

```bash
make up          # Запустить контейнеры
make down        # Остановить контейнеры
make build       # Пересобрать образы
make migrate     # Применить миграции
make test        # Запустить тесты (PHPUnit)
make lint        # Проверить код (Pint, PHPStan, ESLint, Prettier)
make fix         # Автоисправление форматирования
make shell       # Консоль внутри контейнера app
make logs        # Логи контейнеров
```

## Стек технологий

- **Backend:** PHP 8.2+ / Laravel 11
- **Frontend:** React (TypeScript) / Vite
- **БД:** SQLite
- **Очереди:** Redis + Laravel Queue
- **Генерация Word:** PHPWord
- **LLM:** Anthropic Claude API / OpenAI API
- **Контейнеризация:** Docker Compose

## Участие в разработке

Мы рады вкладу в проект! Ознакомьтесь с [CONTRIBUTING.md](CONTRIBUTING.md) перед отправкой pull request.

## Лицензия

Проект распространяется под лицензией [MIT](LICENSE).
