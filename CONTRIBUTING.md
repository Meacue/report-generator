# Участие в разработке

Рад приветствовать ваш вклад в Report Generator. Ниже дано краткое описание как вы можете участвовать в разработке.

## Начало работы

1. Сделайте форк репозитория
2. Клонируйте форк и создайте ветку для своих изменений:

```bash
git clone https://github.com/your-username/report-generator.git
cd report-generator
git checkout -b feature/my-feature
```

3. Разверните проект локально (см. «Быстрый старт» в [README.md](README.md))

## Стандарты кода

### Backend (PHP)

- PHP 8.2+, Laravel 12, строгая типизация (`declare(strict_types=1)`)
- Архитектура — DDD: бизнес-логика в `backend/app/Domain/<Context>/`, адаптеры к внешним системам в `backend/app/Infrastructure/<Context>/` (см. [AGENTS.md](AGENTS.md))
- Use Cases — `final readonly` Action-классы в `Domain/<Context>/Actions/` с одним публичным методом `__invoke()`
- Внешние клиенты реализуют интерфейс из `Domain/<Context>/Services/` (например, `Bitrix24ClientInterface` → `Infrastructure/Bitrix24/Bitrix24Client`)
- Стиль кода — PSR-12, проверяется через [Laravel Pint](https://laravel.com/docs/pint)
- Статический анализ — PHPStan level 10 + Larastan
- Английский язык в коде и комментариях

### Frontend (TypeScript)

- TypeScript strict mode, функциональные компоненты
- ESLint + Prettier для проверки и форматирования

### Проверка перед коммитом

```bash
make lint   # проверка без исправлений
make fix    # автоисправление форматирования
make test   # запуск тестов
```

Код с ошибками линтера или непройденными тестами не будет принят.

## Сообщения коммитов

Используем [Conventional Commits](https://www.conventionalcommits.org/):

## Pull Request

1. Убедитесь, что `make lint` и `make test` проходят без ошибок
2. Опишите, что меняет ваш PR и зачем
3. Если PR связан с issue — укажите номер (`Closes #42`)
4. Один PR — одна логическая задача

## Сообщения об ошибках

Если нашли баг — создайте issue с описанием:

- Что ожидали
- Что произошло
- Шаги для воспроизведения
- Версия PHP, Docker, ОС

## Предложения новых возможностей

Создайте issue с тегом `enhancement` и опишите:

- Какую проблему решает предложение
- Как вы видите решение
- Есть ли альтернативы

## Кодекс поведения

Участвуя в проекте, вы соглашаетесь соблюдать [Кодекс поведения](CODE_OF_CONDUCT.md).
