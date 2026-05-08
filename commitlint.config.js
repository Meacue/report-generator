/**
 * Conventional Commits validator config for commitlint.
 *
 * Spec: https://www.conventionalcommits.org/en/v1.0.0/
 * The mapping of types to CHANGELOG sections is defined in release-please-config.json.
 *
 * Allowed commit types:
 *   feat     — new functionality (bumps MINOR; pre-1.0 — bumps PATCH per SemVer §4)
 *   fix      — bug fix (bumps PATCH)
 *   perf     — performance, no API change (CHANGELOG: Changed)
 *   refactor — refactor with no behavior change (CHANGELOG: Changed)
 *   revert   — revert of a previous commit (CHANGELOG: Removed)
 *   deps     — dependency updates (CHANGELOG: Changed, visible)
 *   security — security patch (CHANGELOG: Security)
 *   docs     — documentation (hidden from CHANGELOG)
 *   chore    — housekeeping (hidden)
 *   test     — adding/fixing tests (hidden)
 *   ci       — CI configuration (hidden)
 *   build    — build / Docker (hidden)
 *   style    — formatting (hidden)
 *
 * BREAKING CHANGE — declared in the footer or with an exclamation mark after the type
 *   (feat!: ..., fix!: ...). Before 1.0.0 it does not bump MAJOR (see SemVer §4),
 *   after 1.0.0 it must bump MAJOR.
 *
 * Examples:
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
