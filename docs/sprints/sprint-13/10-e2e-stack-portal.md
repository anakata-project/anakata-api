# Task 10 · anakata-api · The e2e stack learns the portal
**Repo:** anakata-api (`tests/e2e/`) · **Sprint:** 13 · **Needs:** task 06 (a portal that builds).

## Goal
`up.sh` brings up four apps, and the gate checks five repositories, so portal scenarios can be walked like any other.

## Read first
- `tests/e2e/README.md`, `bin/_lib.sh` (`ensure_sibling`, `install_env`), `bin/install.sh`, `bin/up.sh`, `bin/status.sh`, `bin/down.sh`, `bin/reset.sh`, `bin/gate.sh`, `bin/ledger.sh`, and the Sprint 10 harness section of that sprint's REPORT

## Do
1. **Siblings.** `install.sh` clones or updates `anakata-portal` beside the other three, with the same fetch-and-checkout rule and the same dirty-tree refusal.
2. **Run it.** `up.sh` builds and starts the portal on port **3002** in preview mode, waits for `http://localhost:3002/login`, and prints it in the summary. `status.sh`, `down.sh` and the memory check include it. The API's testing env gains the portal URL for CORS, Sanctum's stateful domains and the invitation links.
3. **Gate and ledger.** `gate.sh` takes five refs (api, panel, engine, ui, portal) and applies the same ancestor-and-empty-diff rule to each; `ledger.sh` records five SHAs per run and counts a result only when all five pass. The run template's code-gate block gains the fifth row.
4. **Reset.** `reset.sh` is unchanged except that the snapshot key also hashes nothing new — say so explicitly, because the portal adds no seed data of its own; agency users are seeded by the existing agencies seeder, which gains one accepted portal user with a known password for the scenarios (documented in `fixtures/accounts.md` beside the staff logins).
5. **Docs.** `README.md` §1 and §3, `RUN_PROMPT.md`, and the batch list get the portal: what it is, its port, and that portal scenarios are B16 and B17.

## Don't
- Don't change application code.
- Don't give the seeded portal user a staff role or permission.
- Don't skip the four-app memory check; raise the warning threshold if the machine needs it, and say so.

## Checks
- `bin/batch.sh --check` and `bin/lint-scenarios.sh` pass.
- A full `install.sh` + `up.sh` on a clean machine reaches ALL UP with four apps; `status.sh` and `down.sh` agree; `reset.sh` still prints its time.
- `gate.sh` fails when any one of the five repositories is off its ref.

## Report
Append **Task 10**: the fifth repository in the gate and ledger, the port and env additions, the seeded portal user, the memory result, and the docs updated. Git commands listed, not run.
