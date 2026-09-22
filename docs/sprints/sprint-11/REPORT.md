# Sprint 11 · Report

## Task 01 · Alerts inbox

One inbox. Alert code inserts and updates `alerts`, `alert_notifications`, and `change_history`. The sweep does not change a booking, payment, task, or delivery.

### Schema
`alerts` and `alert_notifications` share one migration. Both models use `HasAuditColumns` and `SerializesDatesAsUtc`, and `delete()` throws. There is no audience column. `guest_response_id` is an unsigned bigint with no foreign key. `base_key` is indexed. `idempotency_key` is unique. `alert_notifications.attempts` is the extra column the retry-once rule needs. State is derived: open when both timestamps are null, acknowledged when acknowledged and not resolved, resolved when `resolved_at` is set. History uses the morph key `alert`.

### Registry
`AlertKind` plus `AlertRegistry`. A user sees a kind when they hold any audience permission. Admin holds every permission. `emails()` is true only for `CRITICAL`, so these five stay `WARN` and do not email. Carolina sees all five. The CFO sees only `WIRE_NOT_RECEIVED`. Mateo and Lucía see none of these five.

| Kind | Severity | Audience | Section | Base key |
|---|---|---|---|---|
| `OVERDUE_BALANCE` | WARN | `bookings.overdue_decision` | rms | `overdue:{booking}:{dueDate}` |
| `COMMISSION_CAP` | WARN | `commissions.override_cap` | rms | `cap:{booking}` |
| `WIRE_NOT_RECEIVED` | WARN | `payments.mark_wire_received` | rms | `wire:{payment}` |
| `SLA_BREACH` | WARN | `records.act_on_any` | crm | `sla:{task idempotency key}` |
| `DELIVERY_FAILED` | WARN | `sync.retry` | crm | `delivery:{document}` or `delivery:{booking}:{kind}` |

The first three look up `crm_tasks.idempotency_key` with the base key. SLA points at the breached task. Delivery has no task. Sentences use the booking reference and integer money.

### base_key
`idempotency_key` is the base key, or `{base}#{n}` when every existing row for that base key is resolved (`n` is the row count plus one). The suffix marker is `#` because keys already contain colons. `RaiseAlert` looks up by `base_key` under `lockForUpdate`, never with `LIKE`. An unresolved row is returned. A unique-constraint clash re-reads by `base_key` and returns that unresolved row, and writes no second `alert.raised` line. Two back-to-back raises leave one unresolved row. Resolve, then raise twice: exactly one `#2`.

The suite's per-test transaction hides uncommitted rows from a second connection, and a second connection that inserts while `lockForUpdate` is held waits on that lock. The unique-constraint path is forced on the same connection: a `creating` hook inserts the winning row before Eloquent's insert, the insert hits the unique key, and the catch returns that row.

### Sweep
`anakata:alerts` runs every five minutes, `withoutOverlapping`, with `RecordScheduledRuns`, same shape as `anakata:crm-tasks`. One run resolves, then raises, then emails. Replaying it inserts nothing while an unresolved row exists.

Predicates stay in SQL:

- `OVERDUE_BALANCE` uses `Booking::scopeOverdue()`. The due date in the base key is `DATE(Booking::dueDateSql())` selected in that query. `isOverdue()` stays the PHP twin. A test asserts they agree on a mixed set, including a paid booking and a cancelled one.
- `WIRE_NOT_RECEIVED` reads `WireWindow::hours()` once per query, then `status = AWAITING_WIRE AND created_at < now() - hours`. That is `endsAtFor() < now()`. A payment whose window ends at exactly now is not selected. The twin test asserts the SQL predicate and `endsAtFor() < now()` select the same payments, including that boundary.
- `SLA_BREACH`: open system `crm_tasks` with `due_at < now()`.
- `COMMISSION_CAP`: `bookings.status = ON_HOLD_AGENCY`.
- `DELIVERY_FAILED`: `deliveries.status IN (FAILED, BLOCKED)`, excluding a row that already has a later `SENT` of the same document, or the same booking and kind when there is no document.

Resolution is the predicate negated, on unresolved alerts of that kind only.

Listeners, queued and `ShouldDispatchAfterCommit`, raise or resolve the one subject their event names. `BookingOverdueFlagged` is dispatched from `FlagOverdueCommand` after the existing history write; the command stays a history writer. `DeliveryOutcomeRecorded` is dispatched when a delivery is stored as `BLOCKED`, `FAILED`, or `SENT`. `SLA_BREACH`, and an overdue flag cleared without an event, are sweep-only. The `PaymentAwaitingWire` listener raises for that payment only when `endsAtFor()` is non-null and before now. It does not reimplement the hours arithmetic.

### Due date
An `OVERDUE_BALANCE` alert resolves when the booking leaves `scopeOverdue()` (`the overdue flag cleared`). That check comes first, so a future date uses the flag-cleared sentence and raises nothing. If the booking is still overdue and the SQL due date differs from the date in `base_key`, the resolution is `Due date changed to {date}`. The sweep resolves before it raises, so a still-past new date closes the old row and opens the new key in the same run. One unresolved row remains for that booking.

### Wire window
`WireWindow::endsAtFor()` is `created_at` plus `payments.wireWindowHours` calendar hours. It is null unless the status is `AWAITING_WIRE`. It is not business time. The sweep uses `created_at < now() - hours`. The listener uses `endsAtFor()` for its one payment.

### Email
`AlertMailer` runs at the end of the sweep through `Mail::`. The body is the title, the sentence, and `config('anakata.panel_url')` plus the panel path. Nothing else. No `deliveries` row. WARN and INFO are never considered.

It only considers alerts that are `CRITICAL`, not resolved, and have `emailed_at` null. The first attempt computes the audience's active users once and inserts one `alert_notifications` row per user, `SENT` or `FAILED`, `attempts = 1`. That set is the recipient list. Later sweeps only retry that alert's `FAILED` rows with `attempts = 1`, then set `attempts = 2` and leave the row `FAILED` if it fails again. They never insert a new recipient. `emailed_at` is set when every notification row is `SENT` or has `attempts = 2`. A resolved alert is skipped, including before a retry.

No shipped kind is `CRITICAL`. `RaiseAlert` copies severity from the registry. The mail test inserts a `CRITICAL` fixture on a still-overdue booking whose `base_key` is the real overdue key, so the sweep does not clear it before the send.

### Endpoints
`routes/api/alerts.php` is Sanctum plus `active`, mounted at `/api/alerts`. A user with neither `panel.rms` nor `panel.crm` gets 403.

- `GET /api/alerts` filters the list by `state`, `severity`, `kind`, and `section`. Newest `raised_at` first, 25 per page.
- `meta.counts` is open alerts by severity for the caller's audience. It ignores `section`, `state`, `severity`, and `kind`. The topbar calls `GET /api/alerts` without `section`, so the badge is the same number in RMS and in CRM.
- For one user and one dataset, `meta.counts` is identical on the unsectioned call, `section=rms`, and `section=crm`. For Carolina, the total of `meta.counts` equals the open-list pagination `meta.total` for `section=rms` plus `section=crm`.
- `POST /api/alerts/{alert}/acknowledge` is audience-only and open rows only. Acknowledging does not set `resolved_at`.
- `GET /api/alerts/kinds` returns the registry.
- `section=crm` runs `GuardCrmSensitiveData`. The CRM list has no sensitive keys.

Panel paths: booking, overdue, cap, and wire `/rms/reservations/bookings?open={reference}`; delivery `/crm/system/sync`; task `/crm/sales/tasks`.

### No side effect
`tests/Feature/Alerts/AlertsTest.php` counts bookings, payments, `crm_tasks`, and deliveries, and records their statuses, then runs the sweep. The counts and statuses are unchanged. The sweep still inserts the alerts those facts call for.

The sweep query count stays the same after extra bookings that match none of the five predicates.

`anakata:alerts` `*/5 * * * *` is on the CRM-09 job list and in `tests/e2e/fixtures/reference-values.md`. `SyncJobsTest` compares the schedule to the API and stayed green.

`composer check` inside Docker: 1130 tests passed, Pint passed, Larastan passed.

### Notes for later
`docs/requirements/08-dev-decisions.md` and `tests/e2e/scenarios/INDEX.md` were already modified before this task. They are not in the commands below.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Actions/Alerts app/Actions/Documents/SendDocument.php app/Actions/Documents/SendPaymentRequest.php \
  app/Console/Commands/AlertsCommand.php app/Console/Commands/FlagOverdueCommand.php \
  app/Enums/AlertKind.php app/Enums/AlertNotificationStatus.php app/Enums/AlertSeverity.php \
  app/Events/BookingOverdueFlagged.php app/Events/DeliveryOutcomeRecorded.php \
  app/Http/Controllers/Alerts app/Http/Middleware/GuardCrmAlertSection.php app/Http/Resources/Alerts \
  app/Jobs/SendDeliveryJob.php \
  app/Listeners/RaiseAlertsOnBookingCreated.php app/Listeners/RaiseAlertsOnBookingOverdueFlagged.php \
  app/Listeners/RaiseAlertsOnBookingStatusChanged.php app/Listeners/RaiseAlertsOnDeliveryOutcome.php \
  app/Listeners/RaiseAlertsOnPaymentAwaitingWire.php app/Listeners/RaiseAlertsOnPaymentSettled.php \
  app/Mail/Alerts app/Models/Alert.php app/Models/AlertNotification.php app/Models/Booking.php app/Models/Payment.php \
  app/Policies/AlertPolicy.php app/Providers/AppServiceProvider.php app/Support/Alerts \
  app/Support/Crm/EventCatalogue.php app/Support/Schedule/AnakataSchedule.php bootstrap/app.php \
  database/factories/AlertFactory.php database/factories/AlertNotificationFactory.php \
  database/migrations/2026_09_22_210001_create_alerts_tables.php \
  resources/views/mail/alerts routes/api/alerts.php tests/Feature/Alerts \
  tests/e2e/fixtures/reference-values.md tests/e2e/scenarios/crm/CRM-09-sync-jobs-retry.md \
  docs/sprints/sprint-11/REPORT.md
git commit -m "$(cat <<'EOF'
Add the alerts inbox for the five kinds whose facts already exist.

The sweep raises and resolves from SQL, freezes critical-mail recipients on the first attempt, and leaves bookings, payments, tasks, and deliveries unchanged.
EOF
)"
```

## Task 02 · Voyage status and the scheduled jobs

Five scheduled commands. Alerts still go through `RaiseAlert` and `ResolveAlert`. None of them insert or update a payment, execute a refund, move a `CONFIRMED` or `ON_HOLD_AGENCY` booking, or issue a document version.

`CONFIRMED` and `ON_HOLD_AGENCY` on or after the Galápagos departure date share one kind, `CONFIRMED_AT_DEPARTURE`. The sentence names the status (`CONFIRMED` or `ON_HOLD_AGENCY`). It resolves when the booking leaves that status. The unused `OVERDUE` status is ignored. The derived overdue flag does not change this command.

### Voyage status
`anakata:voyage-status` at 00:15 `Pacific/Galapagos`. Cabin and charter bookings, no type filter. The `ON_BOARD` pass finishes, then the `COMPLETED` pass reads status again. A `FULLY_PAID` booking whose return date has already passed becomes `FULLY_PAID` → `ON_BOARD` → `COMPLETED` in that night, with both history rows and both `BookingStatusChanged` events.

- `FULLY_PAID` and Galápagos today ≥ departure date → `ON_BOARD`
- `ON_BOARD` and Galápagos today ≥ `Departure::returnDate()` (`ContactDerived::returnDateSql()`) → `COMPLETED`

Each move is `TransitionBooking` with `system: true` and actor label `System · voyage status`. `History::record` uses that label only when `system` is true and the label is non-empty. Other system transitions stay `System`. A second run the same day selects nothing to move. `RaiseAlert` returns the open row.

`CONFIRMED_AT_DEPARTURE` also resolves from `RaiseAlertsOnBookingStatusChanged` (fact `the booking left {from}`) and from `AlertSweep` when the booking is neither `CONFIRMED` nor `ON_HOLD_AGENCY` (fact `the booking left CONFIRMED or ON_HOLD_AGENCY`). The sweep does not raise `LEDGER_DRIFT`, `COMMISSION_LEAKAGE`, or `LOW_OCCUPANCY`. Critical mail stays on `anakata:alerts`. `emails()` is still true only for `CRITICAL`, so `CONFIRMED_AT_DEPARTURE` and `LEDGER_DRIFT` mail; the other two do not.

### Ledger check
`anakata:ledger-check` at 02:00. Read-only on `payments`. A difference raises `LEDGER_DRIFT`. The next run resolves that key when the difference is gone (`the next ledger run found no difference`). `stripe_events` has no immutability trigger. The drift test updates `payload` directly. The payments trigger still refuses `UPDATE` of `amount`.

Settled card payments are `CARD_STRIPE` and `STRIPE_LINK` (the same settlement bucket as `ReconciliationMatch`), amount > 0, non-null `gateway_id`. Engine checkout writes `STRIPE_LINK`. An applied unmatched charge is `CARD_STRIPE`. Groups are the PaymentIntent: `gateway_id` with any `#{booking_id}` suffix stripped. The group sum is compared to the latest `checkout.session.completed` `amount_total` and currency `usd` through `StripeMoney`. One alert per PaymentIntent. The sentence names every booking reference. `booking_id` is the first of the group. A missing event is a difference.

Each booking with a payment compares `Booking::paidSql()` to `Ledger::paidFresh()`. There is no stored `paid` column.

Refunds are cumulative. Executed card refund rows (`PaymentKind::Refund` linked from `refund_requests.executed_payment_id`, `CARD_STRIPE` or `STRIPE_LINK`) are summed per charge and compared to the latest `charge.refunded` `amount_refunded` and currency. Refund ids are the union across events for that charge. A card refund with no event is a difference, one alert per payment (`ledger:refund:payment:{id}`), because there is no charge id to group on. A wire refund with no Stripe event is not selected.

### Commission scan
`anakata:commission-scan` at 02:30. One `COMMISSION_LEAKAGE` per finding. Resolve fact `the finding is gone`. Cap is `CurrentConfig` `commission.capPct`.

- Sold booking (`ContactDerived::soldStatuses()`) with no `agency_id` whose `channel_of_origin` is one of Travel Advisor, Luxury Agency, Host Agency, Consortia, Tour Operator, Luxury Tour Operator, DMC, Incoming Operator. `CommissionScan::tradeChannels()` is those eight cases. Wholesaler is not in the set.
- Same sold set, no agency, booking request `travel_advisor` true.
- `APPROVED` agency with `commission_pct` above the cap and a booking that is not `ON_HOLD_AGENCY` and not `commission_approved`. `CANCELLED`, `CANCELLED_POSTPAID`, and `RELEASED` are excluded. `ON_HOLD_AGENCY` stays `COMMISSION_CAP`.
- `APPROVED` agency whose `payment_terms` is null or blank.

### Occupancy check
`anakata:occupancy-check` at 07:00. `ON_SALE` departures whose Galápagos date is after today and on or before today plus `alerts.lowOccupancyDaysBefore`. Counts from `Availability`: sellable = sold + held + free. Integer percent `intdiv(sold * 100, sellable)`. Raise `LOW_OCCUPANCY` when that percent is strictly below `alerts.lowOccupancyPct`. Sellable 0 is skipped. Both thresholds come from current business rules.

A departure is resolved when it is not in that raise set: the percent is no longer below, the date is today or earlier, or the status is no longer `ON_SALE`. The stored fact is `occupancy is no longer below the threshold, or the departure has sailed`.

### Document version check
`anakata:document-check` hourly, timezone Galápagos. Latest issued version of invoice, summary, receipt, final invoice, pre-trip, and voucher. Reminders, wire instructions, and the questionnaire are not selected.

If that version has no `SENT` and no `QUEUED` delivery, and no `FAILED` delivery, it calls `SendDocument` with `DeliveryKey::forDocument`. It does not pass `resend`. It never calls `PrepareIssueDocument`.

A `FAILED` key is set back to `QUEUED` at most once, `error` cleared, `SendDeliveryJob` dispatched on the same key after commit, and `delivery.requeued` written on the booking (`document_id`, `delivery_id`, `idempotency_key`). The next run sees that history row and leaves a second `FAILED` as `FAILED`. `DELIVERY_FAILED` stays open for a person. No recipient still records `BLOCKED` through `SendDocument`, and the existing delivery listener raises `DELIVERY_FAILED`.

### Catalogue and schedule
`JobCatalogue` is the doc 07 §7 list: Ledger reconcile → `anakata:ledger-check` (“Drift is reported and never corrected.”), Commission leakage scan → `anakata:commission-scan`, Hold expiry sweep → `inventory:release-expired-holds`, Occupancy check → `anakata:occupancy-check`, Document version check → `anakata:document-check`, Segment recompute → `not needed` (“Not needed: segments are derived in SQL (L2).”), Consent sweep → `not needed` (“Not needed: consent is read at send time from one register (M2).”).

`GET /api/crm/sync/jobs` puts that list on `meta.catalogue`. `data` stays the scheduled commands, so `SyncJobsTest` still expects `data` to equal the schedule. The panel table renders `data`, so the five new commands show up. It does not read `meta.catalogue`. Types regenerate in task 07.

The five commands are on `AnakataSchedule` with `timezone(BusinessTime::zone())`, `withoutOverlapping()`, `onOneServer()`, and `RecordScheduledRuns::attach`. Existing events were left without `onOneServer()`.

### Alert registry

| Kind | Severity | Audience | Section | Resolves when |
|---|---|---|---|---|
| `CONFIRMED_AT_DEPARTURE` | CRITICAL | `bookings.overdue_decision`, `payments.record` | rms | leaves `CONFIRMED` or `ON_HOLD_AGENCY` |
| `LEDGER_DRIFT` | CRITICAL | `payments.record`, `refunds.approve` | rms | the next ledger run finds no difference |
| `COMMISSION_LEAKAGE` | WARN | `agencies.manage` | rms | that finding is gone |
| `LOW_OCCUPANCY` | INFO | `departures.manage`, `rates.manage` | rms | percent no longer below, or the departure has sailed |

Keys: `confirmed-at-departure:{booking}`, `ledger:stripe:{intent}`, `ledger:paid:{booking}`, `ledger:refund:{charge}`, `leak:trade:{booking}`, `leak:advisor:{booking}`, `leak:cap:{booking}`, `leak:terms:{agency}`, `occupancy:{departure}`.

### Tests
`tests/Feature/Operations/` covers the midnight boundary (UTC 05:30 still the previous Galápagos day, 06:30 is departure day), charter, catch-up to `COMPLETED` with both history rows and `BookingStatusChanged` labelled `System · voyage status`, a same-day re-run with no second history row, and `CONFIRMED` / `ON_HOLD_AGENCY` raising the alert without a status change.

Ledger: a clean ledger is silent; two bookings that sum to the event stay silent; a tampered `amount_total` raises one alert naming both; restoring the payload resolves it; two partial refunds that sum to `amount_refunded` stay silent. Payment row count and amounts are unchanged.

Leakage: one alert per finding, the eight channels, Wholesaler excluded, a cancelled over-cap booking silent, resolve when the agency or terms appear. Occupancy: just below the percent, exactly the percent, one day inside and one day outside the window, a sailed departure resolves. Document check: one re-queue on the same key, no new document version, a second `FAILED` left `FAILED`, no recipient raises `DELIVERY_FAILED`.

The catalogue lists all seven doc 07 rows. Every scheduled event still has a run hook. CRM-09 E2 and the Scheduled jobs table in `tests/e2e/fixtures/reference-values.md` include the five commands. `AlertsTest` kinds count is 9.

`composer check` inside Docker: 1145 tests passed, Pint passed, Larastan passed.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Actions/Bookings/TransitionBooking.php \
  app/Console/Commands/CommissionScanCommand.php app/Console/Commands/DocumentCheckCommand.php \
  app/Console/Commands/LedgerCheckCommand.php app/Console/Commands/OccupancyCheckCommand.php \
  app/Console/Commands/VoyageStatusCommand.php \
  app/Enums/AlertKind.php app/Http/Controllers/Crm/SyncController.php \
  app/Support/Alerts/AlertKeys.php app/Support/Alerts/AlertRegistry.php app/Support/Alerts/AlertSweep.php \
  app/Support/Crm/SyncJobs.php app/Support/History/History.php app/Support/Operations \
  app/Support/Schedule/AnakataSchedule.php app/Support/Schedule/JobCatalogue.php \
  tests/Feature/Alerts/AlertsTest.php tests/Feature/Crm/SyncJobsTest.php tests/Feature/Operations \
  tests/e2e/fixtures/reference-values.md tests/e2e/scenarios/crm/CRM-09-sync-jobs-retry.md \
  docs/sprints/sprint-11/REPORT.md
git commit -m "$(cat <<'EOF'
Schedule voyage status and the operational checks from doc 07.

Voyage dates move through TransitionBooking, and ledger, commission, occupancy, and document checks raise alerts without correcting payments or issuing documents.
EOF
)"
```

## Task 03 · Manifests

DPNG and captain's manifests for one departure. Versions are immutable. The daily job stops at the departure date. Guest preferences, the panel table, and MAN-01–03 stay out.

### Rules
`manifests.captain_days` is **7**, status **CONFIRMED** (N4). `manifests.chase_days_before_due` is **10**, status **PENDING CLIENT** (N5). That is the only new flagged row. Registry rows sit beside `dpng-manifest`: `captain-manifest` and `manifest-chase`.

The DML migration publishes through `ConfigPublisher` as System. Approval reference: `Sprint 11: manifests.captain_days added (default 7, source N4); manifests.chase_days_before_due added (default 10, source N5, PENDING CLIENT)`.

Counts move from 81 / 56 / 15 / 10 / 35 to **83 / 58 / 15 / 10 / 36**.

`anakata:config-verify` before the migration named the two missing paths on `business_rules` v2. After migrate, `business_rules` v3 is valid.

Due dates are Galápagos calendar dates. DPNG is the departure date minus `dpng_charter_days` when any counted booking is a charter, otherwise minus `dpng_fit_days`. Captain is the departure date minus `captain_days`. Chase is the DPNG due date minus `chase_days_before_due`.

### Who is on a manifest
`ManifestRoster::passengers()` is the one query. Guests of bookings on that departure in `CONFIRMED`, `ON_HOLD_AGENCY`, `FULLY_PAID`, `ON_BOARD`, `COMPLETED`. Ordered by cabin `sort` (a charter with no cabin last), then `guests.position`. Completeness is `Guest::isComplete()` only.

List status is live, not the stored version. Everyone complete is `READY`. DPNG due date on or before today with anyone incomplete is `OVERDUE DATA`, including after the yacht has sailed. Otherwise `{n} PASSENGER` or `{n} PASSENGERS PENDING`.

### Schema
Table `manifests`. Unique `(departure_id, kind, version)`. Morph alias `manifest`. Files live on a private disk `manifests` (`storage/app/manifests`, `throw => true`), not the financial `documents` disk.

Delete is refused. Update is refused unless the only changes are `purged_at` (null to a timestamp) and the three paths (value to null), plus `updated_at` / `updated_by`. The model throws the same way before the trigger.

### Versions
`snapshot_hash` is sha256 of that kind's row payload. A dietary or medical change changes the captain hash only. A passport change changes both.

`POST` with no prior version is `REQUESTED`. A different hash is `PASSENGER_CHANGE`. The same hash is 200, `created: false`, message `This manifest is unchanged.`, and no new row. An empty passenger set is 422. History `manifest.generated` records counts and reason only. The actor is the user, or `System · manifests` for the job. The job never writes `PASSENGER_CHANGE`.

`FIRST` is written only while Galápagos today is on or after that kind's due date and today is on or before the departure date, and only when that kind has no version. A missed day still catches up once, through the departure date inclusive. The day after departure creates nothing, so the first run after deploy does not backfill long-sailed seed departures. Manual `POST` stays allowed after sailing.

### Sailing cutoff
`anakata:manifests-due` is `dailyAt('06:00')`, `Pacific/Galapagos`, `withoutOverlapping`, `onOneServer`, `RecordScheduledRuns`. It is not a doc 07 catalogue row. It shows on Sync because that list is every scheduled command (`0 6 * * *` in `SyncJobsTest`, `reference-values.md`, and CRM-09).

The alert `MANIFEST_DATA_OVERDUE` is WARN, audience `guests.view_sensitive`, section `rms`, base key `manifest-data:{departure}`. It is raised only while today is on or after the DPNG due date, today is before the departure date, and a counted guest is incomplete. WARN does not email. On the departure date, and on any later run, an open alert resolves with `Departure sailed`. It also resolves earlier with `Passenger data is complete`. A departure-only alert points at `/rms/operations/documents`. The kinds list goes from 9 to 10.

### Columns
DPNG: `#`, Surname, Given names, Nationality (`Countries::name`), Passport, Expiry, DOB, Age (`Age::at` on the departure date), Cabin (`cabins.label`, or `Full yacht` when the booking is a charter with no cabin). The PDF header says Age. CSV and XLSX say **Age at departure**. Cell text is the same in all three: a missing passport is `MISSING`; every other missing value is `—`. Incomplete PDF rows use the prototype miss styling. CSV is UTF-8. XLSX is OpenSpout 4.32 (`openspout/openspout`). No PhpSpreadsheet.

Captain: Cabin, Passenger (name, `(lead)`, booking reference), Nat. (code), Age, Passport, Emergency contact, Dietary, Medical / accessibility. `CaptainParticulars::for(Guest)` reads `dietary_note`, and `medical_note` · `accessibility_note`. Emergency is `—` until task 04. Task 04 is the only later edit of that method. The stored PDF always contains the health fields. The word “restricted” is not written into the file.

### Chaser
Same command, only while today is on or after the chase date and today is before the departure date. Each counted booking that still has an incomplete guest, once. Recipient is the summary rule: lead guest with an email, otherwise the group coordinator or client of record. The link comes from `IssueCompleteAccessToken`. `DeliveryKind::DataChaser` is `DATA_CHASER`, attaches no PDF, and is not a document kind. Idempotency key is exactly `chase:{booking}:{departure}`, including when the delivery is blocked, so a later run does not send. Not a marketing message. No manifest attachment. `DATA_CHASER` is not retried as a document.

### Endpoints
All under `/api/rms`, `panel.rms`. The list and the version list are counts and metadata for every RMS user. Generating and downloading need `guests.view_sensitive` (403 without it).

- `GET /api/rms/manifests?from=&to=` — departures in range that have counted guests. No guest fields.
- `GET /api/rms/departures/{departure}/manifests` — versions.
- `POST /api/rms/departures/{departure}/manifests/{kind}` — allowed after sailing.
- `GET /api/rms/departures/{departure}/manifests/{manifest}/file/{format}` — `pdf`, and for DPNG also `csv` and `xlsx`. Captain plus csv or xlsx is 404. A purged file is 404. Each download writes `manifest.downloaded`.

### Retention
Inside `anakata:retention`, after the guest pass. Every unpurged version of that departure and kind, whoever is in the file. CAPTAIN (dietary, medical, accessibility, plus passport) at the return date plus `retention.medical_days_after_cruise` (90). DPNG (passports, no health notes) at the return date plus `retention.passport_months_after_cruise` (24 months). The same `RetentionWindow::elapsed()` rule as I7: the day after the end date. Files are deleted, paths nulled, `purged_at` set. Rows stay. Dry run writes nothing and reports file counts per kind.

### CRM
`App\Http\Controllers\Crm` must not use `App\Actions\Manifests` or `App\Support\Manifests`. No `/api/crm` route contains `manifest`.

`composer check` inside Docker: 1158 tests passed, Pint passed, Larastan passed.

### Notes for later
Task 04 fills emergency contact, and dietary or medical preferences, only inside `CaptainParticulars::for()`. A change there changes the captain hash.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Actions/Crm/RetryFailedDelivery.php app/Actions/Manifests \
  app/Console/Commands/ManifestsDueCommand.php app/Console/Commands/RetentionCommand.php \
  app/Enums/AlertKind.php app/Enums/DeliveryKind.php app/Enums/ManifestFormat.php \
  app/Enums/ManifestKind.php app/Enums/ManifestReason.php \
  app/Http/Controllers/Rms/ManifestController.php app/Http/Requests/Rms/ManifestIndexRequest.php \
  app/Http/Resources/Rms/BusinessRulesCurrentResource.php \
  app/Http/Resources/Rms/ConfigVersionDetailResource.php \
  app/Http/Resources/Rms/ManifestDepartureResource.php \
  app/Http/Resources/Rms/ManifestVersionResource.php \
  app/Mail/Documents/DataChaserMail.php app/Mail/Documents/DeliveryMailFactory.php \
  app/Mail/Documents/DocumentMail.php app/Models/Alert.php app/Models/Manifest.php \
  app/Policies/ManifestPolicy.php app/Providers/AppServiceProvider.php \
  app/Support/Alerts/AlertKeys.php app/Support/Alerts/AlertRegistry.php \
  app/Support/Alerts/AlertSubject.php app/Support/BusinessRules/Registry.php \
  app/Support/Config/Documents/BusinessRulesDocument.php \
  app/Support/Config/Documents/ManifestsRules.php \
  app/Support/Documents/DeliverySubject.php app/Support/Documents/Recipients.php \
  app/Support/Manifests app/Support/Operations/ManifestsDue.php \
  app/Support/Schedule/AnakataSchedule.php composer.json composer.lock \
  config/filesystems.php routes/api/rms.php \
  database/migrations/2026_09_22_220001_add_manifest_deadlines_to_business_rules.php \
  database/migrations/2026_09_22_220002_create_manifests_table.php \
  resources/views/mail/documents/data-chaser.blade.php resources/views/manifests \
  tests/Arch/ArchTest.php tests/Feature/Alerts/AlertsTest.php \
  tests/Feature/Config/BusinessRulesDocumentTest.php \
  tests/Feature/Config/BusinessRulesEndpointsTest.php tests/Feature/Crm/SyncJobsTest.php \
  tests/Feature/Operations/ManifestsTest.php \
  tests/e2e/fixtures/reference-values.md tests/e2e/scenarios/crm/CRM-09-sync-jobs-retry.md \
  docs/sprints/sprint-11/REPORT.md
git commit -m "$(cat <<'EOF'
Issue immutable DPNG and captain manifests and chase missing passenger data.

The daily job stops at the departure date, and captain files are purged on the medical window while DPNG files wait for the passport window.
EOF
)"
```

## Task 04 · Guest preferences and the hotel-manager brief

The pre-trip questionnaire is versioned guest answers. Restricted answers are encrypted. The documents job sends the link. Guests and staff can save a new version. The hotel-manager brief is rendered on request. The captain's manifest reads the current answers, and retention clears every version on the medical date.

### Questions
`PreferenceQuestions` is the prototype `PREF_Q` list, in that order: `diet`, `breakfast`, `pillow`, `temp`, `bev`, `intensity`, `time`, `interests`, `celebr`, `access`, `emerg`, `first`, `req`. Wording is PENDING the guest-experience team (N6), noted in the class and here. None are required. Dietary is free text. Kosher is not an option. `access` and `emerg` are the only restricted keys. Unknown keys, non-strings, text over 500 characters, and choice values off the list are 422. Empty is allowed.

`GET /api/rms/guest-experience/questions` (`panel.rms`) serves the same list the engine payload embeds.

### Schema
New table `guest_preferences`. `answers` holds the non-restricted keys only. `accessibility` and `emergency_contact` use `SensitiveEncrypted` and are in `SensitiveFields`, so the CRM guard strips them and history redacts them if a value is ever stored under those names. Preference history itself does not store answer text.

A save locks the guest and inserts the next version. It does not update a previous row. Current answers are the latest row with `purged_at` null. A `BEFORE UPDATE` trigger allows only the retention purge (set `purged_at`, clear `answers` and both encrypted columns). There is no delete trigger, so deleting a guest can cascade. `source` is `GUEST_LINK` or `STAFF`.

`booking_access_tokens` gains nullable `guest_id` and `covered_guest_ids`, snapshotted at issue. Purpose `QUESTIONNAIRE`. A complete-page token does not open the questionnaire, and a questionnaire token does not open the complete page. Both mismatches are 404 with `Not found.` Tokens expire at the end of the return date (`BusinessTime::dayEndUtc`), not the departure date.

### Sending
`anakata:documents-due` uses the pre-trip date gate (`documents.pretrip_days_before`, catch-up, confirmed or later). A booking that already has its pre-trip still gets the questionnaire once. `DeliveryKind::Questionnaire` attaches no PDF. Keys are `questionnaire:{booking}:{guestId}` and `questionnaire:{booking}:lead`.

A guest with a usable email gets their own mail and a token whose snapshot is that guest. Guests without a usable email share one mail to the lead guest, else the group coordinator, else billing, else the contact. That token's snapshot is those guests. If nobody can receive it, one blocked delivery is recorded. `--dry-run` lists and writes nothing. The link is `{engine_url}/questionnaire/{token}`.

The document-plan row is no longer the Sprint 11 placeholder. Trigger is `T−{pretrip_days_before}`. Status follows the same schedule and delivery facts as the pre-trip row, aggregated across that booking's questionnaire deliveries (failed, then blocked, then queued or due, then sent). No Preview, Issue, or Resend. DOC-01 E7 for ANK-2026-0003 stays `23 Sep 2027`, status `BLOCKED` or `SCHEDULED`.

### Guest link
Same middleware as the complete page (`throttle:engine-complete`, `noindex`).

`GET /api/engine/questionnaire/{token}` returns the booking reference, departure date, itinerary name, the questions, and only the snapshotted guests (first name and cabin). Current non-restricted answers are returned. `access` and `emerg` are the string `provided` or empty.

`PUT .../guests/{guest}` inserts one version, source `GUEST_LINK`, `recorded_by` null, actor label `Guest (self-service)`. 404 when the guest is outside the snapshot, or the token is expired, revoked, or the wrong purpose.

An omitted or empty `access` / `emerg` copies the previous encrypted value onto the new version. A non-empty value replaces it. The guest link cannot clear a restricted answer. Non-restricted keys save what was sent, including empty.

### Staff
Writes need `guest_experience.manage` (`Permission::GuestExperienceManage`, label Record guest preferences, group guests). Manager receives it in `SystemRole` and in a grant migration (`Sprint 11: Manager may record guest preferences`). Admin already has every permission. Sales Exec does not. The own-records rule is not applied.

Reading or writing `access` / `emerg` also needs `guests.view_sensitive`. A staff save without that permission that includes those keys is 403. Omitting them copies the previous values forward. Staff who have the permission clear a restricted answer by sending it empty. That is the only clear path.

`GET /api/rms/departures/{departure}/guest-experience` uses `ManifestRoster` (sold bookings). KPIs: guests on board, booking count, answered/total, send date from `pretrip_days_before` (sent versus scheduled from the date versus today), celebrations, and accessibility-or-medical (preference `access` or guest `medical_note`; the count is visible to everyone). Per guest: name, booking reference, email or `no email — sent to lead guest`, cabin, status `ANSWERED` / `SENT_NO_REPLY` / `SCHEDULED`, dietary, celebration, activity (`intensity` · `time`). Restricted fields are values with `guests.view_sensitive`, otherwise booleans.

`GET` and `PUT /api/rms/guests/{guest}/preferences` return the current answers and the version history. PUT is source `STAFF`, `recorded_by` the actor.

`GET /api/rms/departures/{departure}/hotel-manager-brief` is `text/html`, rendered on request, not stored, not emailed. Sections: dietary, celebrations, accessibility only with `guests.view_sensitive`, special requests, room-and-rhythm counts (pillow, temp, breakfast, intensity, time, first), and how many have not answered. Emergency contact is not on the brief. `?format=pdf` streams that same HTML through `PdfRenderer`. The PDF starts with `%PDF` and is not written to the manifests disk. Fonts are embedded with `DocumentFonts`, extracted from the manifest paper layout. Each successful GET writes one `brief.printed` history row on the departure (actor, format) with no answer text.

Both save paths write `guest.preferences_recorded` on the guest only when a key changed: the changed keys, the version, and the source. No answer text. A no-op still inserts the version and writes no history entry.

### Captain's manifest and retention
`CaptainParticulars` reads the current preference beside the guest notes. Emergency is `emergency_contact`, else `—`. Dietary is preference `diet` and `dietary_note`. Medical is `medical_note`, `accessibility_note`, and preference `accessibility`. `ManifestRoster` eager-loads that row. The next `IssueManifest::request()` sees a different captain hash and writes `PASSENGER_CHANGE`. `first()` does not regenerate. A preference-only change does not change the DPNG hash. Saving an answer does not reissue a manifest.

`anakata:retention` purges every version's `answers`, `accessibility`, and `emergency_contact` on `retention.medical_days_after_cruise` and sets `purged_at`. Rows stay. Guests with unpurged preferences are candidates even when the medical notes are already empty. The count is `preferences_purged` on the existing `retention.applied` entry. Dry run counts only. After the purge, those answer strings are not in `guest_preferences` or `change_history`.

### Separation
No CRM route. `App\Actions\GuestExperience` and `App\Support\GuestExperience` are on the CRM denylist next to manifests.

`composer check` inside Docker: 1170 tests passed, Pint passed, Larastan passed.

### Deviations
The update trigger allows the retention purge and refuses every other update. A delete trigger was not added, because a guest delete must be able to cascade.

`DocumentFonts` was pulled out of `ManifestFiles` so the brief can embed the same faces without writing the manifests disk.

`ResolveCompleteAccessToken` now requires purpose `COMPLETE`, and the complete-page URL lookup ignores questionnaire tokens.

### Open questions
Question wording is still PENDING the guest-experience team (N6). Doc 03's OPS-006 row was not changed; that row is the sales-open date.

### Notes for later
The engine questionnaire page is task 08. The panel Guest Experience screen is task 10. NPS is task 05. GX e2e scenarios are task 12. This task does not reissue a captain's manifest when an answer is saved.

`SendDeliveryJob` records `payment_request.sent` for every non-document delivery, which now includes the questionnaire. That is the same path as the data chaser and was left as it is.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Actions/Complete/ResolveCompleteAccessToken.php \
  app/Actions/Crm/RetryFailedDelivery.php app/Actions/GuestExperience \
  app/Console/Commands/DocumentsDueCommand.php app/Console/Commands/RetentionCommand.php \
  app/Enums/BookingAccessTokenPurpose.php app/Enums/DeliveryKind.php \
  app/Enums/DocumentPlanKind.php app/Enums/Permission.php \
  app/Enums/PreferenceQuestionType.php app/Enums/PreferenceSource.php \
  app/Enums/PreferenceStatus.php app/Enums/SystemRole.php \
  app/Http/Controllers/Engine/QuestionnaireController.php \
  app/Http/Controllers/Rms/GuestExperienceController.php \
  app/Http/Requests/Engine/UpdateQuestionnaireRequest.php \
  app/Http/Requests/Rms/UpdateGuestPreferencesRequest.php \
  app/Http/Resources/Engine/QuestionnaireResource.php \
  app/Http/Resources/Rms/PreferenceQuestionResource.php \
  app/Mail/Documents/DeliveryMailFactory.php app/Mail/Documents/DocumentMail.php \
  app/Mail/Documents/QuestionnaireMail.php \
  app/Models/BookingAccessToken.php app/Models/Guest.php app/Models/GuestPreference.php \
  app/Policies/GuestPreferencePolicy.php app/Services/Documents/DocumentFonts.php \
  app/Support/Documents/DeliverySubject.php app/Support/Documents/DocumentPlan.php \
  app/Support/Documents/Recipients.php app/Support/GuestExperience \
  app/Support/Manifests/CaptainParticulars.php app/Support/Manifests/ManifestFiles.php \
  app/Support/Manifests/ManifestRoster.php app/Support/Roles/GrantGuestExperienceManage.php \
  app/Support/SensitiveFields.php \
  database/factories/GuestPreferenceFactory.php \
  database/migrations/2026_09_22_230001_create_guest_preferences_table.php \
  database/migrations/2026_09_22_230002_add_questionnaire_scope_to_booking_access_tokens.php \
  database/migrations/2026_09_22_230003_grant_guest_experience_manage_to_manager.php \
  resources/views/guest-experience resources/views/mail/documents/questionnaire.blade.php \
  routes/api/engine.php routes/api/rms.php \
  tests/Arch/ArchTest.php tests/Feature/Documents/DocumentPlanTest.php \
  tests/Feature/GuestExperience tests/Feature/Retention/RetentionCommandTest.php \
  tests/Unit/Enums/SystemRoleTest.php tests/Unit/GuestExperience \
  tests/Unit/Support/SensitiveFieldsTest.php \
  tests/e2e/scenarios/documents/DOC-01-confirmed-documents-tab.md \
  docs/sprints/sprint-11/REPORT.md
git commit -m "$(cat <<'EOF'
Record versioned guest preferences and print the hotel-manager brief.

The questionnaire goes out with the pre-trip itinerary, restricted answers stay encrypted, and retention clears them with the medical notes.
EOF
)"
```
