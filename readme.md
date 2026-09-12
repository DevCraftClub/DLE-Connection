Просто шаблон для репозиториев

1. Обновить значения в `manifest.json`
2. Удалить `crowdin.yml`, если нет файлов локализации
3. Обновить данные в `install_archive.(sh|bat)` либо удалить эти файлы
4. Очередь Dependabot (`.github/workflows/dependabot-queue.yml`):
   - Settings → General → Allow auto-merge (по желанию)
   - Опциональный секрет `QUEUE_GITHUB_TOKEN` (PAT) для обхода CODEOWNERS / rulesets
   - В `.github/dependabot.yml` поменять reviewers (`MaximHarder` → владелец репозитория)
   - CI в `dependabot-ci-automerge.yml` подогнать под стек (без `package.json` — только минимальный gate)

