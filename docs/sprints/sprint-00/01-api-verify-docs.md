# Task 01 · anakata-api · Verify documentation paths
**Repo:** anakata-api · **Sprint:** 0 (read `README.md` in this folder first)

## Goal
Every document path the rules refer to is correct, and there is an index of the requirements.

## Read first
- `.cursor/rules/anakata-core.mdc`
- `docs/requirements/` (list it recursively)

## Do
1. List `docs/requirements/` recursively.
2. Check that every path mentioned in `.cursor/rules/anakata-core.mdc` exists:
   - docs 01–08
   - `examples/seed-data.json` and `examples/booking-engine-feed.json`
   - `screenshots/`
   - the RMS prototype, the CRM prototype, the booking-engine prototype and its `SPEC.md`
3. If a file has another name or location, **update the paths in `anakata-core.mdc`** in this repo. Do not move or rename the documents. List the changed paths in the report, because the same file must be updated in the other four repos.
4. Write `docs/requirements/INDEX.md`: every file with its path and a one-line description. Group them as: specs · contract · decisions · prototypes · examples · screenshots.

## Out of scope
Any code.

## Acceptance criteria
- [ ] Every path in `anakata-core.mdc` resolves to a real file.
- [ ] `INDEX.md` lists every file under `docs/requirements/`.
- [ ] `REPORT.md` has a "Task 01" section, including any path corrections.
