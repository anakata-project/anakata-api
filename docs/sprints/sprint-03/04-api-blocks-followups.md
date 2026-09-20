# Task 04 · anakata-api · Internal blocks; Sprint 2 follow-ups
**Repo:** anakata-api · **Sprint:** 3 (read `README.md` in this folder first)
**Needs:** task 03.

## Goal
Staff take cabins off sale for fam trips, maintenance, negotiations and courtesies. A block is the first real claim holder. This task also settles the three Sprint 2 TODOs that were waiting for departures.

## Read first
- `docs/requirements/01-functional-spec.md` §11; `03-business-rules.md` R-B6
- `prototype/rms_index.html`: `v-block` (list columns, reasons, the "＋ New block" alert text: yacht / cabins / departures + reason, Admin/Manager only)
- `docs/requirements/08-dev-decisions.md`: E1–E8 (config documents), **F1**
- The TODOs: `RatesDocument.php` (two `TODO(Sprint 3)`), `EngineSettingsDocument.php` (`TODO(Sprint 3)`), `Registry.php` (OPS-006 "Set in Departures (Sprint 3)")

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Table `internal_blocks`:**
   - `id`, `reference` (`BLK-NNN`: add `ReferenceType::Block`, global counter, pad 3, and list it in the References section of `laravel.mdc`)
   - `reason` (enum `FAM_TRIP` / `MAINTENANCE` / `NEGOTIATION_HOLD` / `COURTESY`, labelled "Fam trip", "Maintenance", "Negotiation hold", "Courtesy"), `notes` (text, max 500)
   - `released_at`, `released_by`, `release_note` (nullable)
   - audit columns, timestamps

   Model `InternalBlock`, morph alias `internal_block`, `claims()` morph-many.
2. **Actions (each writes history on the block):**
   - `CreateInternalBlock`: input `{ reason, notes?, departures: [ { departure_id, cabin_codes: [...] | "ALL" } ] }`, 1–20 departures, cabins `S1`–`S8` / `OWNER` or `ALL` (the whole yacht).
     - Claims everything through `ClaimService::claim` in **one transaction**, all-or-nothing across departures.
     - A conflict returns the 409 with the full list: "Suite 02 on 14 Nov 2027 · ANATIVA is held."
     - History `block.created` with the scope.
   - `ReleaseInternalBlock`: `{ note? }` → `ClaimService::release(block, RELEASED)`, sets `released_at` / `released_by`, history `block.released`. Releasing twice → 409.
   - `UpdateInternalBlockNotes`: reason and notes only. The scope never changes; release and create a new block instead. The UI says so.
   - Blocks are **never deleted** (the audit trail).
3. **Endpoints** under `/api/rms/blocks`. Viewing needs `panel.rms`; changes need `blocks.manage`.
   - `GET /`: filters `status=active|released|all` (default active), `from` / `to` (dates of the blocked departures), `yacht_id`.
   - Each row carries a **scope summary** as in the prototype: "ANATIVA · Suite 07–08 · 14 Nov 2027", "ANAMARA · Full yacht · 31 Oct 2027", or grouped per yacht and date when there are several. Build it with a pure formatter and test it: consecutive suite numbers collapse to a range, the Owner's Suite is listed by name, and all 9 cabins read "Full yacht".
   - Also on each row: reason, notes, the `created_by` name, created and released info, and the claims.
   - `POST /`, `PATCH /{block}`, `POST /{block}/release`, `GET /{block}/history`.
4. **Demo seed (F6, local/testing):** one fam-trip block on the 14 Nov 2027 ANAMARA departure, cabins S7–S8, notes "Virtuoso agents fam — 4 pax", created by System. This matches the prototype's `occ()`. The prototype's static table says ANATIVA, and its maintenance row (31 Oct 2027) has no departure in the seed data; note both inconsistencies in the report.
5. **Sprint 2 follow-ups.** Each is a document-level check that now needs the database. Do them through a small injected service, not static calls from `rules()`, and keep the documents testable.
   - **Rates, error:** a document that removes a year in which departures exist → error on `years`: "Can't remove 2028 — 12 departures sail that year." Removing a year with no departures is fine.
   - **Rates, warning:** departures exist in a year with no rates → "Departures in 2031 have no rates." (it can't come from a removal, which is already an error; this covers departures created for a year with no rates).
   - **Engine settings, warning:** `calendar.default_search_from` is before the first bookable month (the earliest `ON_SALE` departure whose label isn't `NOT_SHOWN`) → the prototype's wording from `esIssues`.
   - **Registry OPS-006:**
     - current display is the first departure date ("First cruise 7 Nov 2027") and the sales-open date stays as the source text
     - `differs` is true when the earliest departure ≠ 7 Nov 2027
     - with no departures, "No departures yet" and `differs: null`
     - keep the PRO-001 note
   - The panel needs **no** change for these: they arrive through `/validate` and the registry.
6. **Tests:**
   - create across two departures (all-or-nothing on conflict, with the message)
   - `ALL` = 9 claims
   - release, and release twice
   - notes-only update
   - the scope formatter cases
   - permissions (Lucía read-only)
   - blocked cabins show as `BLOCKED` in availability and the calendar
   - a departure with an active block can't be deleted (409 counts) but can change date
   - the rates year error and warning, the engine warning, the OPS-006 row with and without departures

## Out of scope
Any panel work (task 09). Holds from bookings (Sprint 4).

## Acceptance criteria
- [ ] Creating a block over S1–S3 on two departures shows six `BLOCKED` cells in `/api/rms/calendar`. Releasing it frees them, and the block stays listed under `status=released`.
- [ ] Publishing rates without 2027 is refused while the demo departures exist.
- [ ] `composer check` passes.
- [ ] A "Task 04" section in `REPORT.md` covering:
  - the scope-summary rules
  - the seed-data inconsistencies
  - the exact texts of the new rates and engine checks
