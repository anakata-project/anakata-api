# E2E 01 · Browser harness for cloud agents

## What was built

A layer above Pest: scripts that bring up API + panel + engine, written browser scenarios for Sprints 1–2, a cloud-machine definition, and one real example run. No application code changed.

Entry point: [`tests/e2e/README.md`](../../tests/e2e/README.md).

## The scripts

All under `tests/e2e/bin/`, bash, `set -euo pipefail`, `COMPOSE_PROJECT_NAME` defaults to `anakata-e2e`.

| Script | Role |
|---|---|
| `install.sh` | Idempotent siblings + pnpm + `compose pull --policy missing` |
| `up.sh` | Memory check, networks, copy `.env` if missing, staged compose, composer, `key:generate` if empty, Horizon wait, `reset.sh`, frontend preview |
| `reset.sh` | `migrate:fresh --seed`, four demo users, Redis flush, Mailpit empty, `anakata:config-verify`. Refuses unless project is `anakata-e2e` or `E2E_ALLOW_RESET=1` |
| `status.sh` | One line per service; `ALL UP` only if everything answers |
| `down.sh` | Stop preview PIDs + compose down. `--wipe` uses the same destructive guard |
| `mail-latest.sh` | Newest Mailpit message to an address: subject + `http(s)` links (30 s) |
| `db-check.sh` | Read-only tinker JSON; refuses save/update/delete/create/insert/truncate/`DB::statement` |

**Isolation.** Compose project `anakata-e2e` → volume `anakata-e2e_mysql-data`. Working checkout stays `anakata-api_mysql-data`. `container_name` values still collide, so the working stack must be down while e2e is up.

**Startup order.** `up -d --wait mysql redis mailpit` → `up -d app` → `composer install` → `key:generate` if `APP_KEY` empty → wait for `/api/health` and `horizon:status` → `reset.sh`. Do not `--wait` the app first (health is 500 until vendor + key).

**Memory.** `E2E_MIN_MEM_GB` (default 6): warn and continue. Fail only below 3 GiB.

**Frontends.** Default is build + `node .output/server/index.mjs` on 3001 / 3000 (`setsid`, so they survive the installer shell). `E2E_MODE=dev` uses `pnpm dev`. `up.sh` fails if the port is already taken.

**Horizon.** `/api/health` `queue: ok` is Redis `Queue::size()`, not a worker. `up.sh` also waits for `php artisan horizon:status` so invitation/reset mail is sent.

## Environment decisions

`.cursor/environment.json` follows [Cursor cloud-agent setup](https://cursor.com/docs/cloud-agent/setup) as of 19 Sep 2026:

```json
{
  "build": {
    "context": "..",
    "dockerfile": "../tests/e2e/environment/Dockerfile"
  },
  "install": "tests/e2e/bin/install.sh",
  "start": "sudo service docker start || (sudo dockerd > /tmp/dockerd.log 2>&1 &)"
}
```

**Deviation from the task’s JSON:** `dockerfile` and `context` are relative to `.cursor/`. The task’s `"dockerfile": "tests/e2e/environment/Dockerfile"` would resolve under `.cursor/tests/…`. Docs special-case `..` as the repo root, so the file is `../tests/e2e/environment/Dockerfile`. `install` still runs from the project root. `start` is only the Docker daemon — not `up.sh`.

Dockerfile: Ubuntu 24.04, Docker Engine + compose (`fuse-overlayfs`, `iptables-legacy`, `ubuntu` in `docker` + sudo), Node 22 + corepack, `python3` (VIS-01 prototype server). No PHP on the machine.

**Fallback** (dashboard agent-driven setup) is in `tests/e2e/README.md`. Only secret: `GH_TOKEN` if the org repos are private.

**Other small deviations**

- `api.testing.env` — task listed only `api.env`; needed so `.env.testing` can be created without overwriting a developer file.
- `compose pull --policy missing` — a full pull of `mailpit:latest` hung for minutes on a machine that already had the image. Missing-only is idempotent; a fresh cloud disk still pulls everything.
- `ensure_pnpm` also looks at `~/.config/nvm/…/corepack` when `corepack` is not on PATH (this host’s `/usr/bin/node` is v26 without corepack).
- Preview is `node .output/server/index.mjs` with `PORT`/`HOST`, not `pnpm preview`, so the server is not a child of a pnpm process that can exit.
- `db-check.sh` uses word-boundary matching so `created_at` is not treated as `create`.

## Screen vs sprint-report wording

Scenarios follow the **screen**. Recorded mismatches:

| Sprint report / AC | Screen / API |
|---|---|
| Password min 12 | **8** (D1a, `Password::min(8)`, hint `At least 8 characters.`) |
| `Delete bookings` | **Delete reservation** |
| Limited operator disable Carolina → 409 | **403** `This action is unauthorized.` |
| History “You — Invited as Manager” on one line | Two lines: `{time} · {actor}` then the sentence |
| Conflict `"Someone published a newer version (v3)…"` | Full string includes `while you were editing. Reload to see it; your changes were not saved.` |
| Lucía rates “view only” | `VIEW ONLY — ADMIN / DIRECTOR EDITS RATES` (hard-coded) |
| Lucía engine view-only | `VIEW ONLY — SALES EXEC` (role name) |
| Login labels | i18n sentence case; CSS renders them uppercase |

## Verification (isolated copy)

Working stack was `docker compose down` (volumes kept). Four repos copied to `/tmp/anakata-e2e-verify` **without** `.env`, `.env.testing`, frontend `.env`, or `vendor/`.

| Check | Result |
|---|---|
| `install.sh` twice | Idempotent. Second run reused lockfiles and local images. |
| First `up.sh` | No `vendor/`. `.env` copied from `api.env` with empty `APP_KEY`. `key:generate` ran. `ALL UP`. |
| Memory | 9.34 GiB available — no warn, no fail. |
| `status.sh` | `ALL UP` once ports 3000/3001 were free. |
| `reset.sh` twice | Four demo users; `anakata:config-verify` valid for all three documents. |
| Forgot-password + `mail-latest.sh` | Subject `Reset your Anakata password`; reset URL on `:3001`. |
| `db-check.sh` read | `4` (user count). |
| `db-check.sh` write | Exit 1: `refusing write expression`. |
| `down.sh` | Stopped previews + e2e compose. |
| Working MySQL volume | `anakata-api_mysql-data` **created=2026-09-18T13:26:28+03:30** before and after. Untouched. |
| E2e volume | `anakata-e2e_mysql-data` created 2026-09-19T20:29:23+03:30. |

A leftover working-tree `pnpm dev` on **3001** first stole the panel port. `up.sh` now fails if the port is in use. During verification that process was stopped; **restart your local panel `pnpm dev` if you still need it.**

Browser (SMK-01, SMK-02, RATE-03): see [`tests/e2e/runs/example-run.md`](../../tests/e2e/runs/example-run.md). All three **PASS**.

`composer check` on the working checkout: **261 passed**, Pint and Larastan OK.

## Files touched

- `anakata-api`: `tests/e2e/**`, `.cursor/environment.json`, `.gitignore`, `AGENTS.md`, `.cursor/rules/anakata-core.mdc`, `docs/sprints/e2e/REPORT.md`, `docs/sprints/sprint-01/README.md`, `docs/sprints/sprint-02/README.md`
- `anakata-ui` / `anakata-panel` / `anakata-engine`: `.cursor/rules/anakata-core.mdc` (E2E keep-alive paragraph)

## Deviations

Listed under Environment decisions. None change application behaviour.

## Open questions

None.

## Notes for later

- Pin `mailpit` to a digest if `:latest` pull noise becomes a problem on cloud Builds.
- A future sprint can add `terminals` in `environment.json` if Cursor starts forwarding 3000/3001/8000 automatically.
- The verification copy at `/tmp/anakata-e2e-verify` can be deleted.

## Git commands for the user to run

```bash
# anakata-api
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add tests/e2e .cursor/environment.json .gitignore \
  .cursor/rules/anakata-core.mdc AGENTS.md \
  docs/sprints/e2e/REPORT.md \
  docs/sprints/sprint-01/README.md docs/sprints/sprint-02/README.md
git status
git commit -m "$(cat <<'EOF'
Add a browser e2e harness for cloud agents.

Scripts bring up the isolated anakata-e2e stack; scenarios cover sprints 1–2.
EOF
)"

# anakata-ui
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add .cursor/rules/anakata-core.mdc
git commit -m "$(cat <<'EOF'
Point sprint work at the shared e2e scenario catalogue.
EOF
)"

# anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add .cursor/rules/anakata-core.mdc
git commit -m "$(cat <<'EOF'
Point sprint work at the shared e2e scenario catalogue.
EOF
)"

# anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add .cursor/rules/anakata-core.mdc
git commit -m "$(cat <<'EOF'
Point sprint work at the shared e2e scenario catalogue.
EOF
)"
```
