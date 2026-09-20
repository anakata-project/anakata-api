# Task 04 · anakata-api · Status transitions, date change, deletion, audit
**Repo:** anakata-api · **Sprint:** 4 (read `README.md` in this folder first)
**Needs:** task 03.

## Goal
A booking moves only through legal transitions, enforced on the server, each with history and a reason where doc 02 requires one. Claims follow the status (G9). A booking can change date or cabin; a date change reprices at the current rates after staff confirm the difference (G4, prototype Overview). Admins can delete with a reason. Every deletion and released request appears in the "Deleted & released" audit.

## Read first
- `docs/requirements/08-dev-decisions.md`: **G3, G4, G6, G8, G9, G10**
- `docs/requirements/02-data-model.md` → Booking states; `03-business-rules.md` FIN-006, OPS-007
- `prototype/rms_index.html`: `TRANS`, `doTrans` (the reason prompts, which transitions require a reason, the log wording), `delBk`, `AUDIT_DEL` and the audit panel in `v-book`, the Overview tab (`drOverview`): its transition buttons and the "Free date change (FIN-006)" button text, which specifies the repricing
- `app/Services/Inventory/ClaimService.php` (`convert`, `release`), `app/Policies/Concerns/ChecksOwnRecords.php`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **The transition table**, in one place (`App\Support\Bookings\Transitions`), from the prototype's `TRANS` minus what waits for Sprint 5 (G6):

   | From | To |
   |---|---|
   | REQUESTED | PENDING_PAYMENT, CONFIRMED, RELEASED, CANCELLED |
   | PENDING_PAYMENT | CONFIRMED, CANCELLED |
   | CONFIRMED | FULLY_PAID, CANCELLED |
   | FULLY_PAID | ON_BOARD, CANCELLED_POSTPAID |
   | ON_BOARD | COMPLETED |
   | COMPLETED, CANCELLED, CANCELLED_POSTPAID, RELEASED | — |

   - `OVERDUE`, `ON_HOLD_AGENCY` and `WAITLISTED` aren't reachable yet. Keep them in the enum; list them in the report as "Sprint 5 / G7".
   - **Reason required** for CANCELLED, CANCELLED_POSTPAID, FULLY_PAID (manual, as in `doTrans`) and RELEASED; optional otherwise.
   - **Date guards:** ON_BOARD only from the departure date; COMPLETED only from the return date (Galápagos calendar dates).
2. **`TransitionBooking` Action:** `POST /api/rms/bookings/{booking}/transition` `{ to, reason? }`.
   - It needs `bookings.change_status` **and** the own-records rule (`ownsOrMayActOnAny`); the policy returns 403 with "Blocked: own-records rule." (prototype wording).
   - An illegal target → 422 on `to` listing the legal ones.
   - **Claims (G9):**
     - REQUESTED → PENDING_PAYMENT or CONFIRMED: if the booking still has its active HOLD, `ClaimService::convert(booking, booking, BOOKING)`. If the hold has expired (G9, task 05), `claim(... BOOKING)` afresh; a conflict → 409 "The cabin was taken after this request's hold expired." with nothing changed.
     - → RELEASED / CANCELLED / CANCELLED_POSTPAID: `release(booking, RELEASED or CANCELLED)`
     - other transitions don't touch claims
   - **References (G3):** a booking reaching CONFIRMED without a `reference` draws one now (`ReferenceType::Booking`, the year of that moment) and keeps its `request_reference`.
   - **History** `booking.status_changed`: before/after status, the reason, and the prototype's wording, including "(marked manually — USD 23,940 not in the payments record)" for FULLY_PAID with a balance > 0.
   - **Cancellation** in Sprint 4 releases the cabin and records the reason. The penalty, refund request and client notification are Sprint 5. Put a `TODO(Sprint 5)` at that exact spot.
   - The detail response's `allowed_transitions`: `[{ to, reason_required }]`, filtered by the table, the date guards, the permission and the own-records rule for **this** user.
3. **Date or cabin change:** `POST /api/rms/bookings/{booking}/move` `{ departure_id, cabin_code }` (for a charter: `departure_id` only), Action `MoveBooking`.
   - It needs `bookings.move` + own-records. Allowed from REQUESTED, PENDING_PAYMENT, CONFIRMED and FULLY_PAID.
   - The target departure must be in the future. When the festive flag changes, the preview says so (the price changes with it).
   - **Groups:** a booking in a group can change **cabin** within the same departure. Moving it to another departure → 409 "This booking belongs to GRP-007 — moving a group to another departure isn't supported yet." (note for later).
   - **One transaction:**
     1. lock both departure rows in ascending id order (G10)
     2. release the old claims (`MOVED`)
     3. claim the new ones with the same kind (HOLD with the same expiry for a request, BOOKING otherwise)
     4. update the booking
   - A conflict → 409 with the claim message; the booking is unchanged.
   - **Repricing (G4):** the move re-quotes at `CurrentConfig::rates()` for the target (its sailing year and festive flag). The request must include `confirm_total`, the total the user saw in the preview. If it differs from the server's quote (rates published in between), return 409 "The price changed since the preview (USD x → USD y). Review and confirm again." with nothing written. On success the booking takes the new `rates_version_id`, `price_lines` and `total`. `deposit_pct` and `balance_days` stay as sold. A cabin change within the same departure re-quotes too and is normally a zero difference. No modification fee (FIN-006).
   - `balance_due_date` follows the new date.
   - History `booking.moved`: before/after `{ departure: "7 Nov 2027 · ANAMARA", cabin: "Suite 04", total: 26600 }`.
   - **Preview:** `POST /api/rms/bookings/{booking}/move/preview` (same input, no writes) → `{ available, current_total, new_total, difference, new_price_lines, sailing_year_changes: bool, festive_changes: bool, warnings }`. The dialog shows the difference and asks the user to confirm it (G4).
4. **Deletion (G8):** `DELETE /api/rms/bookings/{booking}` `{ reason }`, which needs `bookings.delete` (Admin by default) and a reason (422 without one).
   - Release the claims (`CANCELLED`), soft-delete the booking, history `booking.deleted` with the reason.
   - A deleted booking disappears from lists and returns 404 on detail, but its history stays readable through the audit.
5. **Small edits:** `PATCH /api/rms/bookings/{booking}` `{ internal_notes?, owner_id? }`.
   - Notes need `can_act`.
   - Reassigning the owner needs `records.act_on_any`, and the new owner must be an active user with `panel.rms`.
   - History `booking.updated` / `booking.owner_changed`.
6. **Audit:** `GET /api/rms/bookings/audit?from&to`, from `change_history` where the event is `booking.deleted` or `booking.released`. Rows: `at`, `actor_label`, `reference`, `client` (contact name at the time; put it in the history `after` when writing), `what` ("Reservation deleted" / "Request released — hold returned to inventory"), `why`. Newest first, paginated. It needs `bookings.view_all`.
7. **Tests:**
   - every legal transition and a sample of illegal ones
   - the reasons (required vs optional)
   - own-records (Lucía can't transition Mateo's booking; Carolina can)
   - the date guards
   - the claim effects (REQUESTED → CONFIRMED converts and assigns the reference; REQUESTED with an expired hold → re-claims, or 409 if the cabin was taken; CANCELLED frees the cabin in availability)
   - the FULLY_PAID manual wording
   - move: same departure, other cabin; other departure (repriced, new rates version); across a sailing year (difference as the calculator gives it); into a festive departure (supplement added); a stale `confirm_total` → 409 and nothing written; conflict leaves everything unchanged; group to another departure → 409
   - delete: 403 for Manager, 422 without a reason, soft-deleted with claims released
   - owner reassignment rights
   - the audit list
   - `allowed_transitions` for three users on the same booking

## Out of scope
Payments-driven transitions, OVERDUE and OPS-007, penalties and refunds, agency holds (Sprint 5). Moving a whole group across departures (note for later).

## Acceptance criteria
- [ ] Cancelling a CONFIRMED booking with a reason frees its cabin (the calendar shows `FREE`), and history shows the reason.
- [ ] Moving a booking from 7 Nov to 19 Dec 2027 (festive) adds the festive supplement after confirmation and moves its claim. The old cabin is free, and the new cabin is `SOLD`.
- [ ] `composer check` passes; `/docs/api.json` types every new response.
- [ ] A "Task 04" section in `REPORT.md` covering:
  - the transition table as built
  - the reason rules
  - the move rules
  - the group-move note
  - the Sprint 5 TODO locations
