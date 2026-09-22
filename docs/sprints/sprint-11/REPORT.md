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
