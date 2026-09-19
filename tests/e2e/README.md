# E2E harness — agent entry point

Browser scenarios for a Cursor cloud agent. This does not replace Pest. Do not change application code, tests, seeders or scenarios during a run.

## 1. Start

```bash
tests/e2e/bin/up.sh
```

Wait until the last line is `ALL UP`. If it fails, write an **ENV** failure with the log (`tests/e2e/runs/.logs/` and compose logs) and stop.

`up.sh` is not the cloud `start` command. Always run it yourself and wait.

## 2. Accounts and URLs

Demo users: [`fixtures/accounts.md`](fixtures/accounts.md). Password for all four: `password`.

| What | URL |
|---|---|
| Panel | http://localhost:3001 |
| Engine | http://localhost:3000 |
| Mailpit | http://localhost:8025 |
| API docs | http://localhost:8000/docs/api |
| API health | http://localhost:8000/api/health |

## 3. Choosing scenarios

Catalogue: [`scenarios/INDEX.md`](scenarios/INDEX.md).

Pick by **id**, **tag** (`smoke`, `sprint-1`, `sprint-2`, `auth`, `visual`…) or **priority** (P1 first).

Example prompts: “Run the e2e smoke suite” → tag `smoke`. “Run all sprint-2 scenarios” → tag `sprint-2`.

## 4. For each scenario

1. Run `tests/e2e/bin/reset.sh` unless the scenario says **Start:** `continues from <ID>`.
2. Follow the steps exactly (URLs, field labels, values).
3. Check every **Expected** line (E1, E2, …).
4. Run any **Cross-checks**.

Helpers:

```bash
tests/e2e/bin/mail-latest.sh carolina@anakata.test
tests/e2e/bin/db-check.sh 'App\Models\ChangeHistory::latest("id")->first()'
tests/e2e/bin/status.sh
```

## 5. Agent rules

- **Observe, don't fix.** During an e2e run, never change application code, tests, seeders or scenarios. The only file you create is the run report.
- **One user per browser context.** Sign out, or use a fresh private context, before acting as another user. Scenarios with two users at once say so and use two separate contexts.
- **Evidence for every failure:**
  - a screenshot
  - the browser console errors
  - the failing network request (method, URL, status, response body, cookies not included)
  - the step number
- **Classify every failure:**
  - `BUG`: the product is wrong
  - `ENV`: the stack or machine is wrong
  - `SCENARIO`: the scenario is outdated or ambiguous; quote the line and propose new wording
  - `FLAKY`: passed on a retry; retry once only
- **Never loosen an expectation to make it pass.** If the product deliberately changed, it's a `SCENARIO` finding for a human to decide.
- **Expected values come from the scenario or `fixtures/reference-values.md`**, never from what the screen currently shows.
- **Time:** timestamps are shown in Galápagos time (UTC−6). Compare with that, not the machine's time zone.
- **Don't wait blindly.** Wait for a visible condition (the text, the toast, the row), at most 15 s, then fail the step.
- **Reports:**
  - write them to `runs/YYYY-MM-DD-HHMM-<slug>.md`
  - reports are not committed to `dev`; if the agent works on a branch, it may commit only its report there
  - always paste the summary table into the chat as well

## 6. Writing the report

Copy [`runs/_RUN_TEMPLATE.md`](runs/_RUN_TEMPLATE.md). Name the file `runs/YYYY-MM-DD-HHMM-<slug>.md`. Paste the summary table into the chat.

## 7. Stopping

```bash
tests/e2e/bin/down.sh
```

Volumes stay. `down.sh --wipe` deletes the `anakata-e2e` volumes only (refuses any other compose project).

## Cloud machine fallback

If `.cursor/environment.json` + the Dockerfile do not produce a usable Build, use **agent-driven setup** in the Cursor Cloud Agents dashboard. Tell the setup agent:

> Clone the four anakata repos as siblings, run `tests/e2e/bin/install.sh`, then `tests/e2e/bin/up.sh`, and confirm `tests/e2e/bin/status.sh` passes.

The only secret is `GH_TOKEN` (read access to `github.com/anakata-project`) if the repos are private. `api.env` holds test-only MySQL passwords. `APP_KEY` is generated at `up.sh` time.
