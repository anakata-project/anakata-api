# Task 03 · anakata-api · Manifests
**Repo:** anakata-api · **Sprint:** 11 · **Needs:** task 02.

## Goal
Every departure with passengers gets its DPNG passenger list and captain's manifest on time, as immutable versions, exportable for the park authority and visible only to the people allowed to see passports and health data (N4). Missing data is chased before the deadline (N5).

## Read first
- `docs/requirements/08-dev-decisions.md`: **N4, N5**, B4, I1, I7, J2, J3, J5, N1
- doc 01 §4.5, §6.4
- `prototype/rms_index.html`: `renderDocs` (the Departure manifests table: passengers, completeness bar, DPNG due, captain's manifest date, READY / n PASSENGERS PENDING / OVERDUE DATA, the two buttons), `manifestRows` (which bookings count), `dpngHtml` (columns, "n of m passengers complete", "Column set approved by Anakata, 12 Sep 2026"), `captainHtml` (columns, restricted cell, confidentiality line)
- `Guest` (completeness as the Guests tab computes it), the document rendering pipeline and PDF renderer (J2), `ManifestsRules`, `anakata:retention`, `BookingAccessToken`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Who is on a manifest.** One SQL scope: guests of bookings on the departure in CONFIRMED, ON_HOLD_AGENCY, FULLY_PAID, ON_BOARD or COMPLETED (the prototype excludes cancelled, released and requested), ordered by cabin then position. Completeness uses the same rule as the Guests tab — one helper, not a copy.
2. **Due dates.** DPNG: departure date − `manifests.dpng_fit_days`, or − `manifests.dpng_charter_days` when any counted booking is a charter. Captain's manifest: − `manifests.captain_days` (new, 7). Chaser: DPNG due − `manifests.chase_days_before_due` (new, 10, PENDING CLIENT). Shape change for the two new rules with the usual procedure (DML migration, `config-verify` before and after, registry rows and counts, BR fixtures).
3. **Schema.** `manifests`: `departure_id`, `kind` (DPNG, CAPTAIN), `version` (per departure and kind), `reason` (FIRST, PASSENGER_CHANGE, REQUESTED), `generated_at`, `generated_by` (null for the system), `passengers`, `complete`, `snapshot_hash`, and file paths for PDF, and for DPNG also CSV and XLSX, on a private disk. Immutable: triggers refuse update and delete except the retention job's purge columns (`purged_at`, and nulling the file paths).
4. **Rendering.** PDFs from the prototype layouts through the existing renderer. DPNG CSV and XLSX with exactly the prototype's columns (#, Surname, Given names, Nationality, Passport, Expiry, DOB, Age at departure, Cabin), UTF-8, an incomplete passenger's missing values as `MISSING` / empty, matching the PDF. The captain's manifest carries dietary, emergency contact and medical / accessibility from the guest and from task 04's preferences when present (until task 04 lands, from the guest notes only; task 04 wires the rest).
5. **Generation.**
   - `anakata:manifests-due`, daily 06:00 Galápagos, with the run hooks: on each due date, generate the version FIRST if none exists. On and after the DPNG due date, a departure with incomplete passengers raises WARN MANIFEST_DATA_OVERDUE (audience `guests.view_sensitive`), resolved when complete; the generated version records what was complete.
   - `POST /api/rms/departures/{departure}/manifests/{kind}` (reason REQUESTED, or PASSENGER_CHANGE when the passenger set or a counted field changed since the last version — the API decides from the hash).
6. **Chaser (N5).** In the same command: on the chase date, for each counted booking with an incomplete passenger, send one reminder to the lead guest (or group coordinator) with their complete-page link (existing token and send path), delivery kind `DATA_CHASER` with J5 key `chase:{booking}:{departure}`. Never twice.
7. **Endpoints** (`panel.rms`):
   - `GET /api/rms/manifests?from=&to=` — the prototype's manifests table: departure, yacht, charter flag, passengers, complete count, DPNG due and captain's date with T− labels, status (READY, n PASSENGERS PENDING, OVERDUE DATA), and the latest version of each kind. Counts only; no guest fields. Visible to every RMS user.
   - `GET /api/rms/departures/{departure}/manifests` — versions (kind, version, reason, when, by, complete / passengers).
   - `GET …/manifests/{manifest}/file/{format}` — PDF, CSV, XLSX. **Needs `guests.view_sensitive`**; 403 otherwise. Each download writes history (who, when, which version, format).
8. **Retention.** `anakata:retention` deletes manifest files when the passport data they contain is anonymised (I7) and sets `purged_at`; the version rows stay.
9. **Not reachable from the CRM.** Arch test: CRM controllers may not use manifest classes; no `/api/crm` route returns a manifest.

## Don't
- Don't email a manifest to anyone.
- Don't fill in missing passenger data.
- Don't change an issued manifest version; generate a new one.

## Checks
- `composer check`; `config-verify` before and after.
- The counted set, due dates (FIT and charter), statuses at the boundaries.
- Generation on the due date once; a passenger change gives a new version; an unchanged request says so (no new version).
- CSV / XLSX columns and values equal the PDF's; incomplete rows.
- 403 without `guests.view_sensitive`; download history.
- The chaser sends once per booking.
- Retention purges files and keeps rows.
- The arch test.

## Report
Append **Task 03**: rules added and counts, the schema, generation and versions, the formats and columns, the chaser, the endpoints and permissions, retention. Git commands listed, not run.
