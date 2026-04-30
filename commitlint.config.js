/**
 * Conventional Commits validator config for commitlint.
 *
 * Спека: https://www.conventionalcommits.org/en/v1.0.0/
 * Маппинг типов на секции CHANGELOG задан в release-please-config.json.
 *
 * Допустимые типы коммитов:
 *   feat     — новая функциональность (bump MINOR; pre-1.0 — bump PATCH согласно SemVer §4)
 *   fix      — исправление бага (bump PATCH)
 *   perf     — оптимизация без изменения API (CHANGELOG: Changed)
 *   refactor — рефакторинг без изменения поведения (CHANGELOG: Changed)
 *   revert   — откат предыдущего коммита (CHANGELOG: Removed)
 *   deps     — обновление зависимостей (CHANGELOG: Changed, видимое)
 *   security — патч безопасности (CHANGELOG: Security)
 *   docs     — документация (скрыто из CHANGELOG)
 *   chore    — служебные задачи (скрыто)
 *   test     — добавление/правка тестов (скрыто)
 *   ci       — конфигурация CI (скрыто)
 *   build    — сборка/Docker (скрыто)
 *   style    — форматирование (скрыто)
 *
 * BREAKING CHANGE — указывается в footer или восклицательным знаком после типа
 *   (feat!: ..., fix!: ...). До 1.0.0 не бампит MAJOR (см. SemVer §4),
 *   после 1.0.0 — обязан бампить MAJOR.
 *
 * Примеры:
 *   feat(bitrix): sanitize PII in comment payload
 *   fix(report): correct LLM token counter for Russian text
 *   chore(deps): bump phpoffice/phpword to 1.4.1
 *   feat!: redesign /api/reports response shape
 *
 *     Refs #23
 */
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [
      2,
      'always',
      [
        'feat',
        'fix',
        'perf',
        'refactor',
        'revert',
        'deps',
        'security',
        'docs',
        'chore',
        'test',
        'ci',
        'build',
        'style',
      ],
    ],
    'subject-case': [0],
    'header-max-length': [2, 'always', 100],
    'body-max-line-length': [0],
    'footer-max-line-length': [0],
  },
};
