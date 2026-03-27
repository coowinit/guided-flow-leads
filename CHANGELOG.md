# Changelog

## 1.0.4
- Verified the uploaded source is a functional plugin baseline, not a minimal starter scaffold.
- Added a GitHub Actions release workflow back into the project under `.github/workflows/release.yml`.
- Added `workflow_dispatch` so releases can also be run manually from GitHub Actions.
- Standardized package metadata for release packaging (`version.txt`, `readme.txt`, `CHANGELOG.md`).
- Prepared a clean installable plugin zip from the current uploaded source as the release-ready package.

## 1.0.3
- Upgraded Flow Settings into a dynamic step editor.
- Added Add Step, Delete Step, Move Up/Down, and Add Option actions.
- Switched the flow engine to read dynamic step definitions from settings.
- Preserved leads, sessions, and front-end guided flow behavior.
