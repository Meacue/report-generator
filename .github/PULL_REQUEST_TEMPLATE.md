<!--
Заголовок PR должен быть в формате Conventional Commits, например:
  feat(bitrix): sanitize PII in comment payload
  fix(report): correct LLM token counter for Russian text
  chore(deps): bump phpoffice/phpword to 1.4.1

Это становится сообщением единственного коммита после squash-merge,
что напрямую влияет на CHANGELOG.md и SemVer-bump.
-->

## Что меняется

<!-- Кратко: что сделано в этом PR. Один логический change. -->

## Зачем

<!-- Какую проблему решает. Ссылка на issue. -->

Closes #

## Как проверить

<!-- Шаги для ручной проверки или ссылка на тесты. -->

## Чеклист

- [ ] Имя ветки соответствует Conventional Branch (`<type>/<slug>` или `<type>/<issue#>-<slug>`)
- [ ] Заголовок PR — в формате Conventional Commits
- [ ] `make lint` и `make test` проходят локально
- [ ] Документация обновлена (если затрагивает поведение, видимое пользователю)
- [ ] Один логический change в PR (не смешано несколько задач)
