# Sprint 0 · Report
Each task appends its section below.

## Task 01 · Verify documentation paths

### What was built
Path audit of every document referenced in `.cursor/rules/anakata-core.mdc` against `docs/requirements/`. Three path references in the rules file were wrong (the documents were not moved). `docs/requirements/INDEX.md` lists every file under `docs/requirements/`. `08-dev-decisions.md` was missing at the start of the task and was added by the user; the rules already pointed at it as the highest-authority document, so no rules change was needed for it.

### Files touched
- `.cursor/rules/anakata-core.mdc`
- `docs/requirements/INDEX.md`
- `docs/sprints/sprint-00/REPORT.md`

### Path corrections
Apply the same three edits in the other four repos' `anakata-core.mdc`:

- `prototype/index.html` → `prototype/rms_index.html`
- `prototype/crm.html` → `prototype/crm_index.html`
- booking-engine `SPEC.md` → `booking_engine_SPEC.md` (with `booking_engine_README.md` and prototype `prototype/booking_engine_index.html`)

### Deviations
`04-booking-engine-contract.md` is grouped under **contract** in `INDEX.md` (with `07-three-system-integration-contract.md`), not under specs. That matches the grouping decided when the task was planned.

### Open questions
None.

### Notes for later
The four sibling repos (`anakata-ui`, `anakata-rms`, `anakata-crm`, `anakata-engine`) each carry a copy of `anakata-core.mdc` with the same three stale paths. Out of scope for this task; update them in those repos' sprint tasks or a follow-up.
