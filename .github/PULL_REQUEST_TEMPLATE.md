<!--
The PR title must follow Conventional Commits, for example:
  feat(bitrix): sanitize PII in comment payload
  fix(report): correct LLM token counter for Russian text
  chore(deps): bump phpoffice/phpword to 1.4.1

It becomes the message of the single commit produced by squash-merge,
which directly drives CHANGELOG.md and the SemVer bump.
-->

## What changes

<!-- Briefly: what this PR does. One logical change. -->

## Why

<!-- The problem this solves. Link to the issue. -->

Closes #

## How to verify

<!-- Manual verification steps or a link to tests. -->

## Checklist

- [ ] Branch name follows Conventional Branch (`<type>/<slug>` or `<type>/<issue#>-<slug>`)
- [ ] PR title follows Conventional Commits
- [ ] `make lint` and `make test` pass locally
- [ ] Documentation updated (if user-visible behavior is affected)
- [ ] One logical change in the PR (multiple tasks not mixed)
