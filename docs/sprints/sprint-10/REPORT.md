# Sprint 10 · Report

## Task 01 · anakata-api · Sprint 9 follow-ups

### Structured email conflict
`PATCH /api/crm/contacts/{contact}` still returns 409 with the same `message`. The body now also includes `conflicting_contact: { id, name }` via `EmailConflictException`, documented in OpenAPI by `EmailConflictExceptionToResponseExtension`. The old behaviour was message text only, which the panel parsed with `contact #(\d+)`. That parser stays until task 08.

Test: `tests/Feature/Crm/ContactEndpointsTest.php` (409 message and `conflicting_contact`). Schema: `tests/Feature/OpenApi/CrmResponseSchemasTest.php`.

### Contact id on the activity stream
Each `GET /api/crm/activity` row adds `contact_id`. Anonymous rows stay `contact: "anonymous"` and `contact_id: null`. The old behaviour was a display name only, so the panel looked the person up by name.

Test: `tests/Feature/Crm/EngineActivityTest.php`. Schema asserts `contact_id` is nullable.

### Ingest time zone
`IngestBehaviouralEvents` converts `occurred_at` to UTC before clamping and formatting (D6). The old behaviour formatted the parsed offset's wall clock into a timezone-naive column, so `+05:00` and `Z` for the same instant could differ.

Test: `tests/Feature/Engine/IngestEventsTest.php` — an event sent with `+05:00` is stored at the same instant as its `Z` form.

### Schedule registration
Schedule definitions moved from `routes/console.php` into `App\Support\Schedule\AnakataSchedule::register()`. The provider calls it on boot, and `routes/console.php` calls it again; registration is once per process. `SyncJobs` no longer `require`s `routes/console.php`. The old behaviour loaded the console file inside an HTTP request when the schedule was empty.

Tests: the existing "every scheduled command has a recording hook" test still passes. `sync jobs lists every command over HTTP without the console routes loaded` clears the schedule, registers only through `AnakataSchedule`, and asserts the HTTP list matches.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Exceptions/EmailConflictException.php \
  app/Http/Controllers/Crm/ContactController.php \
  app/Support/OpenApi/EmailConflictExceptionToResponseExtension.php \
  app/Support/Schedule/AnakataSchedule.php \
  app/Actions/Contacts/UpdateContact.php \
  app/Actions/Engine/IngestBehaviouralEvents.php \
  app/Support/Crm/EngineActivity.php \
  app/Support/Crm/SyncJobs.php \
  app/Http/Resources/Crm/EngineActivityItemResource.php \
  app/Providers/AppServiceProvider.php \
  config/scramble.php \
  routes/console.php \
  tests/Feature/Crm/ContactEndpointsTest.php \
  tests/Feature/Crm/EngineActivityTest.php \
  tests/Feature/Crm/SyncJobsTest.php \
  tests/Feature/Engine/IngestEventsTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/e2e/bin/_lib.sh \
  tests/e2e/bin/up.sh \
  docs/sprints/sprint-09/REPORT.md \
  docs/sprints/sprint-10/REPORT.md
git commit -m "$(cat <<'EOF'
Close the Sprint 9 follow-ups the CRM panel was parsing around.

Email conflicts and activity rows are structured, event times are UTC, and the schedule is registered at boot so HTTP no longer loads the console routes.
EOF
)"
```

## Task 02 · anakata-api · The consent register and the send-time gate

### Schema and triggers
`contact_consents` is append-only. A content change or a delete raises `contact_consents is append-only`. A merge has to move `contact_id`, so the update trigger allows that column alone to change (`updated_at` and `updated_by` may follow). Every other column must stay as inserted. `session_id` is an extra nullable column, indexed with `contact_id`, so stitching the same session twice records ANALYTICS once.

### Feeds and capture points
`RecordContactConsent` is the only writer. It appends a row and writes `contact.consent_changed` with purpose and granted. The IP is never in that history line.

| Feed | Capture point | Notes |
|---|---|---|
| I6 `RecordConsent` when the document is MARKETING | ENGINE and PAYMENT_LINK → ENGINE_FORM; STAFF → STAFF | Same version, time and IP. Withdrawal sets `granted = false`. Same transaction. |
| Stitch (`StitchEngineIdentity`) | ENGINE_BANNER | ANALYTICS granted. `captured_at` is the session's earliest event that is not `identity.stitched`. Version is `legal.consent_versions.analytics`. Once per contact and session. |
| `POST /api/crm/contacts/{contact}/consents` | STAFF | `how_obtained` required. Version defaults to the published marketing or analytics text; profiling, remarketing and WhatsApp have none, so the version is required. Staff IP is stored null. Not copied into the I6 booking log. |
| Backfill migration | BOOKING_LOG_BACKFILL | See below. |

The complete-page declarations request accepts an optional `withdrawn` list. The engine does not send it. A test posts marketing in that list and gets `granted = false` on both the I6 row and the register, capture point ENGINE_FORM.

### Backfill
`BackfillContactConsents` copies every I6 MARKETING row that does not already have a register row with that `source_consent_id`. `granted = !withdrawn`, same time, version and IP. The migration calls it. On a fresh database the count is 0 because no I6 rows exist yet. Rows written through `RecordConsent` after this code already have a register row, so the backfill skips them. The test inserts two factory MARKETING rows and asserts the inserted count equals the I6 MARKETING count, then asserts a second run inserts 0.

### Shape change and registry
`legal.consent_versions.analytics` default `v1 (pending LEG-002)`. Approval: `Sprint 10: legal.consent_versions.analytics added (default v1 (pending LEG-002), source LEG-002)`. Registry row `consent-analytics`. Counts: all 72, here 47, other pages 15, locked 10, differs or flagged 26. `BR-01` and `reference-values.md` use those numbers and stay ⚠ UNVERIFIED.

The Sprint 6 consent-versions migration's hard-coded defaults now include `analytics` as well. Its feature test re-runs `up()` and `ConfigPublisher` validates against the current rules, which require the key. Databases that already applied that migration do not re-run it. The Sprint 10 migration still adds the key when it is missing.

### Gate and summary
`ConsentGate::allows(contact, purpose)` is true only when the latest row for that purpose is granted. No purpose is transactional. No send exists yet; every future non-transactional send must call this and nothing else. Tested for every purpose, a withdrawal after a grant, and a merge (survivor rows plus loser rows, latest wins) and unmerge.

`ContactConsentSummary` and `ContactDerived::marketingConsentSql()` read the register's latest MARKETING row. `consent=marketing` on the contacts list matches the register's MARKETING granted count. Transactional on the register is not stored; its count is non-merged contacts, the same population as the contacts list.

### Endpoints
All under `/api/crm`, `panel.crm`, sensitive-data guard.

- `GET /api/crm/consents/register` — transactional first (basis Contract, opt-in not needed, captured at booking request), then MARKETING, PROFILING, REMARKETING, WHATSAPP, ANALYTICS. Basis Consent and opt-in needed for those five. Where captured today: engine form and staff, engine banner, or `not captured yet` for profiling, remarketing and WhatsApp. Contacts is the number whose latest row is granted.
- `GET /api/crm/contacts/{contact}/consents` — current state for every purpose (granted null when there is no row) and history newest first. `ip_present` is true or false. The address is not in the JSON.
- `GET /api/crm/consents/data-map` — doc 07 §8 as this system implements it, with the business-rule key and the current value when one sets the retention. Passport uses `retention.passport_months_after_cruise`. Notes use `retention.medical_days_after_cruise`. Behavioural events use `retention.behavioural_raw_months` (unstitched days named in the text). Date of birth and nationality are not purged and are never on a CRM response. Conversations are not built. The register itself is listed as kept and outside the retention job.
- Staff write needs `consents.record`. Admin already passes every permission. The grant migration and Manager's default list add it. Sales Exec does not have it and receives 403.

The contact timeline shows register lines (purpose, granted or withdrawn, capture point) and no longer shows I6 MARKETING lines.

### Checks
`php artisan test` 1078 passed. Pint and Larastan then passed after a phpdoc indent fix and a timeline detail cleanup.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/Permission.php \
  app/Enums/SystemRole.php \
  app/Enums/ConsentPurpose.php \
  app/Enums/ConsentCapturePoint.php \
  app/Models/ContactConsent.php \
  app/Policies/ContactPolicy.php \
  app/Actions/Crm/RecordContactConsent.php \
  app/Actions/Consents/RecordConsent.php \
  app/Actions/Contacts/StitchEngineIdentity.php \
  app/Support/Crm/ConsentGate.php \
  app/Support/Crm/ContactConsentSummary.php \
  app/Support/Crm/ContactDerived.php \
  app/Support/Crm/ContactReferences.php \
  app/Support/Crm/ContactTimeline.php \
  app/Support/Crm/BackfillContactConsents.php \
  app/Support/Crm/ConsentRegister.php \
  app/Support/Crm/ConsentDataMap.php \
  app/Support/Crm/ConsentVersionForPurpose.php \
  app/Support/Crm/ContactConsentView.php \
  app/Support/Roles/GrantConsentsRecord.php \
  app/Support/Config/Documents/ConsentVersions.php \
  app/Support/Config/Documents/BusinessRulesDocument.php \
  app/Support/BusinessRules/Registry.php \
  app/Http/Controllers/Crm/ContactConsentController.php \
  app/Http/Controllers/Engine/CompleteReservationController.php \
  app/Http/Requests/Crm/RecordContactConsentRequest.php \
  app/Http/Requests/Engine/RecordCompleteDeclarationsRequest.php \
  app/Http/Resources/Crm/ConsentRegisterRowResource.php \
  app/Http/Resources/Crm/ConsentDataMapRowResource.php \
  app/Http/Resources/Crm/ContactConsentsResource.php \
  app/Http/Resources/Engine/EngineSettingsResource.php \
  routes/api/crm.php \
  database/migrations/2026_09_22_200001_create_contact_consents_table.php \
  database/migrations/2026_09_22_200002_add_analytics_consent_version_to_business_rules.php \
  database/migrations/2026_09_22_200003_grant_consents_record_to_manager.php \
  database/migrations/2026_09_22_200004_backfill_contact_consents_from_marketing_log.php \
  database/migrations/2026_09_21_200035_add_consent_versions_to_business_rules.php \
  tests/Unit/Enums/SystemRoleTest.php \
  tests/Feature/Crm/ConsentRegisterTest.php \
  tests/Feature/Crm/ContactMergeTest.php \
  tests/Feature/Crm/ContactDerivedTest.php \
  tests/Feature/Crm/ContactEndpointsTest.php \
  tests/Feature/Config/AddAnalyticsConsentVersionToBusinessRulesMigrationTest.php \
  tests/Feature/Config/AddConsentVersionsMigrationTest.php \
  tests/Feature/Config/BusinessRulesEndpointsTest.php \
  tests/Feature/Config/BusinessRulesDocumentTest.php \
  tests/Feature/Engine/EngineCheckoutTest.php \
  tests/Feature/Engine/EngineCompleteReservationTest.php \
  tests/Feature/Engine/EngineFeedTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md \
  tests/e2e/fixtures/reference-values.md \
  docs/sprints/sprint-10/REPORT.md
git commit -m "$(cat <<'EOF'
Record contact consent in one append-only register and gate.

Marketing, analytics and staff captures append a row the CRM can read without an IP address, and future non-transactional sends will ask ConsentGate.
EOF
)"
```

## Task 03 · anakata-api · Deals and the pipeline

### Schema
`deals` stores contact, owner (nullable), title, type (`FIT`, `GROUP`, `CHARTER`, `AGENCY`), a stored stage only for `NEW_LEAD`, `QUALIFYING`, `QUOTED`, `NEGOTIATION` and `LOST`, `stage_entered_at`, an integer estimate, a lost reason, and at most one of `booking_id` or `group_id`. `charter_enquiry_id` is unique. `deals` is a contact-bearing table, so merge and unmerge move it. The morph map key is `deal`.

### Projection
`DealStages` is one SQL `CASE`, used by the board, the drawer and the KPIs. Unbound `LOST` stays lost. A bound deal whose live bookings are all cancelled or released is `LOST`. Any `COMPLETED` is `WON_COMPLETED`. Any `CONFIRMED`, `FULLY_PAID`, `ON_BOARD` or `OVERDUE` is `BOOKING_CONFIRMED`. Any `REQUESTED`, `PENDING_PAYMENT` or `ON_HOLD_AGENCY` is `DEPOSIT_PENDING`. Otherwise the stored stage is used. A mixed group follows the furthest live booking. Bound value is the charges total (cancelled and released count zero) and is labelled `FROM RMS`. Unbound value is the estimate, labelled `CRM ESTIMATE`. Neither bound value nor stages 5–7 are stored.

### Creation and binding
`BookingCreated` (after commit) is dispatched from a request, a manual reservation and engine checkout. The queued listener binds the contact's single open deal in stages 1–4, or opens a new bound deal when there is none or more than one. A group of cabins is one deal. A charter booking is type `CHARTER`. `CharterEnquiryReceived` opens an unassigned `NEW_LEAD` charter deal, idempotent per enquiry. That event is in place for task 04; task 04 should listen to it rather than declare it again. Staff can create an unbound deal, take an unassigned one, and bind a booking or group of the same contact (aliases resolved).

### Moves
`pipeline.move_stage` is required. An unassigned deal must be taken first. Someone else's deal needs `records.act_on_any`. A bound deal returns 422: `This deal follows booking {reference} — change it in the RMS.` The target is a stored stage. `LOST`, and reopening a lost deal, require a reason. History is `deal.stage_changed` with from, to and reason. A move does not write a booking, a hold or a payment.

### SLA and probabilities
New lead is 4 business hours, qualifying 5 business days (the entry day does not count), quoted uses the existing 24-hour response SLA, negotiation is 7 business days. `ok` / `warn` at 75% of the wall-clock window / `bad` at the deadline. A Friday 17:00 new lead is due Monday noon; a Monday holiday pushes it to Tuesday. Bound stages and computed lost are `SYSTEM-SET`. Probabilities are 5 / 15 / 35 / 55 / 80 from `crm.pipeline`, and 100 / 0 in code for confirmed, won and lost. Registry counts are **80 / 55 / 15 / 10 / 34**. The Sprint 9 segment migration now also writes `crm.pipeline` when it republishes, so its test still produces a valid document. BR-01 and `reference-values.md` stay ⚠ UNVERIFIED.

### Endpoints
`GET /api/crm/pipeline` (filters `owner`, `type`, `q` apply to the columns only), `GET /api/crm/pipeline/stage-map`, `GET /api/crm/deals/{deal}`, `POST /api/crm/deals`, assign, bind, and `PATCH` stage. Every CRM user sees every deal. Cash KPIs use the same ledger as Payments & Revenue: collected, awaiting first payment, and overdue match `PaymentsKpis::ledger()`. `scheduled_in` is the balance of confirmed and fully paid bookings. Open pipeline and the weighted forecast are stages 1–5. The query count stays flat as deals are added. Deal opened, bound, stage changed and marked lost appear on the contact timeline.

### Checks
`composer check` ran the suite at 1090 passed and 1 failed: `PipelineResource` had no OpenAPI properties. The resource return types were then spelled out; `CrmResponseSchemasTest` passed (175 assertions). Pint is clean (1158 files). Larastan reports no errors. Pipeline tests were re-run after the `DealStages` rename (9 passed). A later case in that projection test puts a `RELEASED` booking in **Lost**; that test passed on its own.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/DealType.php \
  app/Enums/DealStage.php \
  app/Models/Deal.php \
  app/Events/BookingCreated.php \
  app/Events/CharterEnquiryReceived.php \
  app/Listeners/OpenDealOnBookingCreated.php \
  app/Listeners/OpenDealOnCharterEnquiryReceived.php \
  app/Actions/Crm/OpenDealForBooking.php \
  app/Actions/Crm/OpenDealForCharterEnquiry.php \
  app/Actions/Crm/CreateUnboundDeal.php \
  app/Actions/Crm/AssignDeal.php \
  app/Actions/Crm/BindDeal.php \
  app/Actions/Crm/MoveDealStage.php \
  app/Actions/Bookings/CreateReservation.php \
  app/Actions/Bookings/CreateBookingRequest.php \
  app/Actions/Checkout/SubmitEngineCheckout.php \
  app/Actions/Charter/CreateCharterEnquiry.php \
  app/Support/Crm/DealStages.php \
  app/Support/Crm/DealSla.php \
  app/Support/Crm/DealProbabilities.php \
  app/Support/Crm/DealStageMap.php \
  app/Support/Crm/PipelineBoard.php \
  app/Support/Crm/DealDrawer.php \
  app/Support/Crm/OpenDealReference.php \
  app/Support/Crm/ContactTimeline.php \
  app/Support/Crm/ContactReferences.php \
  app/Support/Crm/EventCatalogue.php \
  app/Support/Crm/FieldOwnership.php \
  app/Support/Payments/PaymentsKpis.php \
  app/Support/Config/Documents/PipelineRules.php \
  app/Support/Config/Documents/CrmRules.php \
  app/Support/Config/Documents/BusinessRulesDocument.php \
  app/Support/BusinessRules/Registry.php \
  app/Http/Controllers/Crm/DealController.php \
  app/Http/Requests/Crm/StoreDealRequest.php \
  app/Http/Requests/Crm/AssignDealRequest.php \
  app/Http/Requests/Crm/BindDealRequest.php \
  app/Http/Requests/Crm/MoveDealStageRequest.php \
  app/Http/Resources/Crm/PipelineResource.php \
  app/Http/Resources/Crm/DealResource.php \
  app/Policies/DealPolicy.php \
  app/Providers/AppServiceProvider.php \
  routes/api/crm.php \
  database/migrations/2026_09_22_200005_create_deals_table.php \
  database/migrations/2026_09_22_200006_add_pipeline_rules_to_business_rules.php \
  database/migrations/2026_09_21_200076_add_crm_segment_thresholds_to_business_rules.php \
  tests/Feature/Crm/PipelineTest.php \
  tests/Feature/Crm/DealSlaTest.php \
  tests/Feature/Crm/ContactMergeTest.php \
  tests/Feature/Crm/SyncOwnershipAndEventsTest.php \
  tests/Feature/Config/AddPipelineRulesToBusinessRulesMigrationTest.php \
  tests/Feature/Config/BusinessRulesEndpointsTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md \
  tests/e2e/fixtures/reference-values.md \
  docs/sprints/sprint-10/REPORT.md
git commit -m "$(cat <<'EOF'
Project the sales pipeline from bookings and let staff move early stages.

Stages 5–7 and bound cash follow the ledger, and a stage move cannot change a booking.
EOF
)"
```

## Task 04 — Tasks and SLAs

System tasks are raised from RMS state and closed when that state clears. Completing or cancelling a task writes the task row, its history, and at most one contact activity. Bookings, payments, refund requests and enquiries are not written from any task path.

### Schema
`crm_tasks` holds title, context, nullable links (contact, deal, booking, charter enquiry, refund request, payment, subject request), owner, `needs_permission`, due time, source, kind, idempotency key, status, close fields and outcome, plus audit columns. `subject_request_id` has no foreign key until task 05. `contact_activities` holds kind, body, occurred time, and optional deal and task links. Both tables are on the merge and unmerge list. Morph keys are `crm_task` and `contact_activity`.

### Kinds as built

| Kind | Key | Due | Owner · needs | Auto-closes when |
|---|---|---|---|---|
| REQUEST_RESPONSE | `request:{booking}` | created + response SLA, business hours | booking owner | the booking leaves REQUESTED |
| CHARTER_QUOTE | `charter:{enquiry}` | received + response SLA, business hours | the enquiry's deal owner, or unassigned | the enquiry leaves NEW |
| OVERDUE_DECISION | `overdue:{booking}:{balance due date}` | end of the Galápagos day it was flagged | booking owner · `bookings.overdue_decision` | the overdue flag clears |
| COMMISSION_CAP | `cap:{booking}` | + 1 business day from the hold | booking owner · `commissions.override_cap` | the booking leaves ON_HOLD_AGENCY |
| WIRE_WINDOW | `wire:{payment}` | end of the wire window (calendar hours) | booking owner · `payments.mark_wire_received` | the payment leaves AWAITING_WIRE |
| REFUND_DECISION | `refund:{request}` | + refund SLA business days | booking owner · `refunds.approve` | the request leaves PENDING |
| DEAL_QUOTE | `deal-quote:{deal}:{stage entered, UTC}` | stage entered + response SLA | deal owner | the deal leaves QUOTED |
| MANUAL | none | the time staff set | the assignee | staff cancel it |
| SUBJECT_REQUEST | — | — | — | raised in task 05 |

`RaiseTask` returns the existing row for a key, including an auto-closed one, and does not reopen it. Auto-close sets the outcome to exactly `Resolved in the RMS` and records the clearing fact on `task.auto_closed`. A system task cannot be cancelled.

### Events and sweep
`RefundRequested` is dispatched from `CreateRefundRequest`. `PaymentAwaitingWire` is dispatched from `RecordPayment` when the payment is awaiting a wire. `CharterEnquiryReceived` already existed from task 03; the task listener is registered after the deal listener so the charter task can take the deal owner. `BookingCreated` and `BookingStatusChanged` also raise and close request and commission-cap tasks. Entering or leaving QUOTED calls the sweep from `MoveDealStage`.

`anakata:crm-tasks` runs every five minutes (`*/5 * * * *`) through `AnakataSchedule`, with the scheduled-run hook. The sweep raises any of the seven system kinds whose current state still calls for a task, then auto-closes open system tasks whose condition has cleared. CRM-09 and `reference-values.md` list the new job and stay ⚠ UNVERIFIED.

### Visibility and completion
`mine` is tasks the user owns plus tasks whose `needs_permission` they hold. `all` requires `records.act_on_any` (Admin passes). `unassigned` is owner null. Priority is `bad` when due, `warn` when due today in Galápagos, otherwise `ok`. Completing an open task writes one `TASK_COMPLETED` activity. The activity rule rejects a passport-like body and a date of birth, and the JSON does not echo the value. Task and activity responses pass the sensitive-field walk. System raises, completions and auto-closes appear on the contact timeline (`Task raised`, `Task completed`, `Task auto-closed`).

### Checks
`composer check`: 1097 passed (8027 assertions), Pint clean (1190 files), Larastan no errors. A Friday 17:00 response due is not a weekend, and a Monday holiday pushes a one-business-day due from Friday to Tuesday. A later test releases a request through `RaiseTasksOnBookingStatusChanged` and auto-closes the response task without the sweep; that test passed on its own.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/TaskKind.php \
  app/Enums/TaskSource.php \
  app/Enums/TaskStatus.php \
  app/Enums/ActivityKind.php \
  app/Models/CrmTask.php \
  app/Models/ContactActivity.php \
  app/Events/RefundRequested.php \
  app/Events/PaymentAwaitingWire.php \
  app/Listeners/RaiseTasksOnBookingCreated.php \
  app/Listeners/RaiseTasksOnBookingStatusChanged.php \
  app/Listeners/RaiseTasksOnCharterEnquiry.php \
  app/Listeners/RaiseTasksOnRefundRequested.php \
  app/Listeners/RaiseTasksOnPaymentAwaitingWire.php \
  app/Actions/Crm/RaiseTask.php \
  app/Actions/Crm/CloseTask.php \
  app/Actions/Crm/SaveManualTask.php \
  app/Actions/Crm/RecordContactActivity.php \
  app/Actions/Crm/MoveDealStage.php \
  app/Actions/Payments/RecordPayment.php \
  app/Actions/Refunds/CreateRefundRequest.php \
  app/Support/Crm/TaskDue.php \
  app/Support/Crm/TaskSweep.php \
  app/Support/Crm/TaskList.php \
  app/Support/Crm/ContactTimeline.php \
  app/Support/Crm/ContactReferences.php \
  app/Support/Crm/EventCatalogue.php \
  app/Support/Crm/FieldOwnership.php \
  app/Support/Schedule/AnakataSchedule.php \
  app/Http/Controllers/Crm/TaskController.php \
  app/Http/Requests/Crm/StoreManualTaskRequest.php \
  app/Http/Requests/Crm/UpdateManualTaskRequest.php \
  app/Http/Requests/Crm/CompleteTaskRequest.php \
  app/Http/Requests/Crm/StoreContactActivityRequest.php \
  app/Http/Resources/Crm/TaskListResource.php \
  app/Http/Resources/Crm/ContactActivityResource.php \
  app/Policies/CrmTaskPolicy.php \
  app/Rules/NoPersonalRecord.php \
  app/Console/Commands/CrmTasksCommand.php \
  app/Providers/AppServiceProvider.php \
  routes/api/crm.php \
  database/migrations/2026_09_22_200007_create_crm_tasks_table.php \
  tests/Feature/Crm/CrmTasksTest.php \
  tests/Feature/Crm/ContactMergeTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/e2e/scenarios/crm/CRM-09-sync-jobs-retry.md \
  tests/e2e/fixtures/reference-values.md \
  docs/sprints/sprint-10/REPORT.md
git commit -m "$(cat <<'EOF'
Raise CRM tasks from RMS state and close them when that state clears.

Completing a task records an activity and never writes a booking, payment, refund or enquiry.
EOF
)"
```

## Task 05 — Subject requests

Subject requests live under `/api/privacy` with `privacy.manage`. That permission is Admin-only: it is not written onto the Manager or Sales Exec roles, and Admin already passes every permission. The routes are outside `/api/crm` because an access export reads bookings, payments, documents and deliveries. CRM controllers cannot use `App\Actions\Privacy`, and they do not reference the privacy controllers. Every privacy JSON response still passes the sensitive-field guard.

### Shape
`privacy.request_sla_days` is 30 calendar days, pending LEG-002. Registry counts are **81 / 56 / 15 / 10 / 35**. `subject_requests` records the contact, type, channel, received time, due time, how identity was checked, status, outcome and a private export path. `crm_tasks.subject_request_id` now references that table. `erasure_log` keeps the contact id, the SHA-256 of the normalised email, when and who.

### Types as built
Creating a request raises one `SUBJECT_REQUEST` task, key `subject:{id}`, owner the creator, needs `privacy.manage`, due with the request. The sweep raises a missed one and auto-closes it when the request leaves OPEN.

- **Access.** `POST …/export` writes a ZIP of JSON to the private disk. It includes the contact, the consent register, booking consent rows, bookings where they are client or group coordinator (reference, dates, status, charges, payments as kind, amount, date and status), documents (kind, version, number, date), deliveries (kind, status, date), activities, task titles and outcomes, stitched behavioural events, and merge rows. It states that passenger data is provided separately pending LEG-002. The file is walked for sensitive keys before it is stored. `GET …/export` downloads it. The retention command deletes the file `privacy.request_sla_days` after completion.
- **Rectification.** Completes with an outcome that names the fields. The contact change stays on `PATCH /api/crm/contacts/{contact}`.
- **Objection.** Completion withdraws MARKETING, PROFILING and REMARKETING through `RecordContactConsent` at capture point `SUBJECT_REQUEST`. The gate is false at the next check.
- **Erasure.** Requires the contact's email typed back. Refused with 409 while a booking return date has not passed, or while a refund is pending or approved. Otherwise the name becomes `Erased contact #{id}`; email, phone, `phone_e164`, both touches and country are cleared; language and type stay. Behavioural events for the contact and those sessions are deleted. Activities are replaced by one `Erased on {date}` line. Issued documents, payments, both consent logs and bookings stay. Guest passport and notes are left to the retention job; the outcome says when. A later booking with the same email is a new contact and inherits no consent.
- **Reject.** An outcome and a verification sentence, then the task auto-closes.

Received and closed lines appear on the contact timeline, with the type and the outcome sentence, not the export.

### Checks
`composer check`: 1104 passed (8114 assertions), Pint clean (1211 files). Larastan then needed `created_by` on the subject-request model; a follow-up analyse reported no errors. `config-verify` fails on a document without `privacy` and passes after the migration publishes version 2.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/Permission.php \
  app/Enums/SubjectRequestType.php \
  app/Enums/SubjectRequestChannel.php \
  app/Enums/SubjectRequestStatus.php \
  app/Models/SubjectRequest.php \
  app/Models/ErasureLog.php \
  app/Actions/Privacy \
  app/Actions/Crm/RaiseTask.php \
  app/Support/Config/Documents/PrivacyRules.php \
  app/Support/Config/Documents/BusinessRulesDocument.php \
  app/Support/BusinessRules/Registry.php \
  app/Support/Crm/TaskSweep.php \
  app/Support/Crm/ContactTimeline.php \
  app/Http/Controllers/Privacy \
  app/Http/Requests/Privacy \
  app/Http/Resources/Privacy \
  app/Console/Commands/RetentionCommand.php \
  app/Providers/AppServiceProvider.php \
  bootstrap/app.php \
  routes/api/privacy.php \
  database/migrations/2026_09_22_200008_add_privacy_request_sla_to_business_rules.php \
  database/migrations/2026_09_22_200009_create_subject_requests_table.php \
  tests/Feature/Privacy/SubjectRequestsTest.php \
  tests/Feature/Config/AddPrivacyRequestSlaToBusinessRulesMigrationTest.php \
  tests/Feature/Config/BusinessRulesEndpointsTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/Arch/ArchTest.php \
  tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md \
  tests/e2e/fixtures/reference-values.md \
  docs/sprints/sprint-10/REPORT.md
git commit -m "$(cat <<'EOF'
Record subject requests outside the CRM and carry them out in one place.

Access exports omit passenger data, and erasure keeps the ledger while it clears the contact.
EOF
)"
```

## Task 06 — Campaigns and the delivery log

Campaigns are a CRM table. They do not add a business-rules field, so the registry counts stay **81 / 56 / 15 / 10 / 35**. `campaigns.manage` is on the Manager role (defaults and `GrantCampaignsManage`). Sales Exec can read. Admin already passes every permission, so the Admin role row is unchanged. CRM controllers cannot use `App\Actions\Offers`. Nothing here creates or edits an offer.

### Schema
`campaigns`: name, nullable `offer_id`, nullable `utm_campaign` (stored lower-case and trimmed, unique when set), audience (max 500), `media_spend` (integer USD, default 0), status `ACTIVE` or `ARCHIVED`, `owner_id`, audit columns. A campaign needs an offer or a UTM key. History events are `campaign.created`, `campaign.updated` and `campaign.archived`. A spend change is one `campaign.updated` line with the integer before and after. Spend is not ledger money. The morph map key is `campaign`.

### Measures
`GET /api/crm/campaigns` reads the offer live (code, name, type, value text, channel, derived status including EXPIRED, booking and travel windows) and computes the measures in one SQL query per campaign list:

| Measure | SQL source |
|---|---|
| redeemed | Sold bookings (`ContactDerived::soldStatuses()`: CONFIRMED, FULLY_PAID, ON_BOARD, COMPLETED, OVERDUE), not soft-deleted, whose `promo_code` or a `price_lines` `code` equals the offer code, case-insensitive. A zero-amount value-add line still matches on the code. |
| revenue | `Booking::chargesTotalSql()` (I9: `bookings.total` plus extras plus fees collected) of those redeemed bookings. |
| attributed first / last | Same sold set, `LOWER(utm_first.campaign)` or `LOWER(utm_last.campaign)` equals the stored key. Count and charges total. A booking can sit in redeemed and in a touch. |
| trade | Redeemed bookings with `agency_id` set. |
| ROAS | `ROUND(revenue / media_spend, 1)` in SQL. Null when spend is 0. The decimal comes back as a string. |
| sends, clicks | Null. `meta.notes.sends` is `Marketing email is not built yet`. |

`GET /api/crm/campaigns/{id}/bookings` is the same match, paginated, with reference, departure date, status, charges total, and which of redeemed / first touch / last touch the row counts in.

`GET /api/crm/campaigns/offers-without-campaign` is stored LIVE and PENDING offers that no ACTIVE campaign covers. Derived EXPIRED is dropped in PHP with `Offer::isDerivedExpired()`, because that status is not a column.

`GET /api/crm/campaigns/attribution-model` is the prototype table as implemented: UTM on the frozen booking `utm_first` and `utm_last`, main channel and channel of origin written once on the booking, offer code on price lines and `promo_code`, partner on the booking and the commission ledger. The conflict sentence says trade wins for commission and marketing keeps the touch.

### What is not measured
Sends and clicks stay null because marketing email is not built. Opens and downloads are not on the delivery log either: `meta.notes.engagement` is `Opens and downloads are not tracked (LEG-002)`.

### Delivery log
`GET /api/crm/deliveries` is every delivery, newest first, paginated. Each row has the booking id and reference, the contact's name, the document kind label, version and reason when a document is attached, otherwise the delivery kind label (payment link, reminder), channel `EMAIL` (WhatsApp stays TEC-005), status, sent or created time, the error's first line or the blocked reason, a recipient count (`JSON_LENGTH` of `to` only), triggered by System or the user's name, and whether a later document of the same booking and kind has a higher version. Addresses, PDFs, snapshots and file paths are not returned. Filters are status, kind, Galápagos `from` / `to` on `COALESCE(sent_at, created_at)`, booking reference and contact name.

`meta.kpis` are computed on the whole log, not the filtered page: sent today (SENT and `sent_at` inside the Galápagos day), failed, blocked, and queued whose `created_at` is older than 15 minutes. That 15 minutes is `DeliveryLog::QUEUED_MINUTES`, a display threshold, not a business rule.

Each row's `rms_path` is `/rms/operations/documents?booking={id}`. There is no resend, render or issue route under `/api/crm`. The Sprint 9 sync retry stays.

### Checks
`composer check`: 1106 passed (8231 assertions), Pint clean (1233 files), Larastan clean.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/Permission.php \
  app/Enums/SystemRole.php \
  app/Enums/CampaignStatus.php \
  app/Models/Campaign.php \
  app/Support/Roles/GrantCampaignsManage.php \
  app/Support/Crm/CampaignMeasures.php \
  app/Support/Crm/AttributionModel.php \
  app/Support/Crm/DeliveryLog.php \
  app/Actions/Crm/SaveCampaign.php \
  app/Actions/Crm/ArchiveCampaign.php \
  app/Policies/CampaignPolicy.php \
  app/Policies/DeliveryPolicy.php \
  app/Http/Requests/Crm/StoreCampaignRequest.php \
  app/Http/Requests/Crm/UpdateCampaignRequest.php \
  app/Http/Controllers/Crm/CampaignController.php \
  app/Http/Controllers/Crm/DeliveryController.php \
  app/Http/Resources/Crm \
  app/Providers/AppServiceProvider.php \
  routes/api/crm.php \
  database/migrations/2026_09_22_200010_create_campaigns_table.php \
  database/migrations/2026_09_22_200011_grant_campaigns_manage_to_manager.php \
  tests/Feature/Crm/CampaignsTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/Unit/Enums/SystemRoleTest.php \
  tests/Arch/ArchTest.php \
  docs/sprints/sprint-10/REPORT.md
git commit -m "$(cat <<'EOF'
Measure campaigns from sold bookings and list what the RMS already sent.

The CRM does not create offers or resend documents.
EOF
)"
```

## Task 07 — Regenerate types, release v0.11.0

Types only. The layer is `0.10.0` → `0.11.0`. `pnpm types:api` regenerated `app/types/api.d.ts` from `http://localhost:8000/docs/api.json`. That file was not edited by hand.

### Prelude
Two responses were still untyped. `TaskListResource` items were `array<string, mixed>`, so the spec emitted an empty object. `GET /api/crm/pipeline/stage-map` returned a raw JSON response. Both now have concrete PHPDoc, and the stage map is `StageMapResource`. `CrmResponseSchemasTest` includes `StageMapResource`. The other Sprint 10 schemas already had properties: the 409 `conflicting_contact`, activity `contact_id`, the consent register, contact consents, the data map, the pipeline and its KPIs, the deal, campaigns, the attribution model, deliveries and their KPIs, and `SubjectRequestResource`.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 11120 | 13022 |
| `app/types/crm.ts` | 105 | 230 |
| `app/types/privacy.ts` | — | 28 |
| `app/types/index.ts` | 286 | 330 |

### Schema → alias

`crm.ts` imports only `./api`. `privacy.ts` imports only `./api`. Neither imports an RMS booking, payment or guest type.

| Alias | Source |
|---|---|
| `ConsentPurpose` | named `ConsentPurpose` |
| `ConsentRegisterRow` | `ConsentRegisterRowResource`, purpose overlaid |
| `ContactConsentState` / `ContactConsentEntry` | current and history rows of `ContactConsentsResource`, purpose overlaid |
| `DataMapRow` | `ConsentDataMapRowResource` |
| `DealStage` | leftover union — mirrors `App\Enums\DealStage`. The schema stores a string. |
| `DealType` | named `DealType` |
| `PipelineColumn` / `PipelineDeal` | `PipelineResource` columns and deals, stage and type overlaid |
| `PipelineKpis` | `PipelineResource.meta.kpis` |
| `StageMapRow` | `StageMapResource` data row, stage overlaid |
| `DealDetail` | `DealResource`, stage and type overlaid |
| `CrmTask` | `TaskListResource` data row, kind overlaid |
| `TaskKind` | leftover union — mirrors `App\Enums\TaskKind` |
| `TaskKpis` | `TaskListResource.meta.kpis` |
| `ContactActivity` | `ContactActivityResource` |
| `Campaign` | `CampaignIndexResource` data row |
| `CampaignMeasures` | the measure fields of that row, including null sends and clicks |
| `AttributionModelRow` | `AttributionModelResource` data row |
| `DeliveryRow` / `DeliveryKpis` | `DeliveryIndexResource` |
| `SubjectRequest` | `SubjectRequestResource`, type, status and channel overlaid |
| `SubjectRequestType` / `SubjectRequestChannel` | named enums |
| `SubjectRequestStatus` | leftover union — mirrors `App\Enums\SubjectRequestStatus` |

Write inputs are the generated request schemas (`RecordContactConsentRequest`, `StoreDealRequest`, `MoveDealStageRequest`, `StoreManualTaskRequest`, `StoreCampaignRequest`, `StoreSubjectRequestRequest`, and the matching update, assign, bind, complete, close and erase bodies).

### Kept leftovers
Sprint 9 leftovers stay (`Segment`, `can_act`, `swapped`, `skipped_rows`, activity `name`). New leftovers are the three string unions above, plus the purpose, stage, type and kind overlays. No runtime list of stages, kinds, purposes or statuses.

### Checks
Layer lint, typecheck, test (35) and build passed. Panel and engine typecheck and build passed against the sibling layer. Engine typecheck and build passed again on the clean tree.

Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}`, working trees overlaid. The ui clone is **0.11.0** and has **no** `app/types/nuxt.d.ts`.

- ui / panel / engine: typecheck pass
- panel / engine: build pass
- **OVERLAY CLONE OK**

The tag is not pushed. Repeat the clone after the commands below, checking out `anakata-ui` at `v0.11.0` with no overlay.

### Git commands
Do not run these in the agent. Explicit paths only. Run in this order.

```bash
# 1. anakata-api prelude
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Crm/TaskList.php \
  app/Http/Resources/Crm/TaskListResource.php \
  app/Http/Resources/Crm/StageMapResource.php \
  app/Http/Controllers/Crm/DealController.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  docs/sprints/sprint-10/REPORT.md
git commit -m "$(cat <<'EOF'
Type the task list and the pipeline stage map.

Scramble was emitting an empty task object and an untyped stage-map response.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/index.ts \
  app/types/crm.ts \
  app/types/privacy.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for the Sprint 10 CRM and privacy responses.

CRM aliases stay on /api/crm schemas. Subject requests live in privacy.ts.
EOF
)"
git tag v0.11.0
git push origin HEAD
git push origin v0.11.0
```

```bash
# 3. pin the panel and the engine
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add nuxt.config.ts README.md
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.11.0.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add nuxt.config.ts README.md
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.11.0.
EOF
)"
```

## Task 08 · anakata-panel · Pipeline and tasks

### Sprint 9 follow-ups
A 409 now uses `conflicting_contact` when the client still has it, and the `contact #(\d+)` parser only when that field is absent (`emailConflictId`). The activity stream opens the contact drawer from `contact_id`. Anonymous rows stay plain text. `formatAttribution` uses the layer's `AttributionTouch`.

`ApiError` on the sibling layer keeps `conflictingContact`. The panel does not require that property, so a typecheck against the published v0.11.0 layer still passes. The regex remains the fallback until a later layer tag carries the field.

### Pipeline
`/crm/sales/pipeline` renders KPIs, eight columns, filters, and the stage map from the API. Cards show the API SLA badge, a lock when `may_move` is false, the RMS reference or “NO RMS RECORD YET”, and the API value with its label. Drag and the keyboard “Move to…” only offer stages 1–4 and LOST. LOST asks for a reason. A refused move shows the API sentence. The panel does not compute cash, stage, or SLA.

The deal drawer shows stage and owner, FROM RMS or CRM ESTIMATE, the RMS record or the no-record sentence, partner and offer when present, attribution, and the contact timeline rows for that deal. Take, log activity, open booking, open contact, bind to one of the contact’s bookings, and “Create quote in the RMS” do not change a booking.

### Tasks
`/crm/sales/tasks` renders KPIs from `meta.kpis`. Tabs are Mine, Unassigned, and All (only with `records.act_on_any`). Closed, kind, and due filter the list. Due text is relative; colour comes from the API priority. System tasks link to the RMS. Complete requires an outcome. A manual task can be created for a contact, with an optional deal and, with `records.act_on_any`, another owner. The hint states that completing a task writes the contact timeline and never the booking.

The contact drawer lists open tasks for that contact and can log an activity. The shared modal sends kind and text. The server stamps the time. A refusal of sensitive-looking text shows the API sentence.

Helpers, tested: `slaBadge`, `taskPriorityClass`, `canDropOn`, `relativeDue`.

### Migration
`2026_09_22_200002_add_analytics_consent_version_to_business_rules` now fills `crm.pipeline` and `privacy.request_sla_days` when the already-published document lacks them, using the same defaults as the later migrations. Current `rules()` require those keys, so republishing a pre-Sprint-10 document otherwise failed validation. The later migrations no-op once the keys exist. The analytics approval string is unchanged.

### Checks
Panel `pnpm lint`, `pnpm typecheck`, `pnpm test` (251), and `pnpm build` passed. The overlay clone at `/tmp/anakata-fresh` (anakata-ui 0.11.0) typechecked and built the panel. Layer unit tests passed (35).

Browser, signed in as Carolina: light theme showed the pipeline KPIs from the payments ledger (collected, scheduled in, awaiting first payment, open pipeline, weighted forecast, overdue) and the eight columns. Dark theme showed the task queue: open 2, breached 0, near 1, system 2, quote SLA 24, with RMS links and Complete on the two system tasks. `reset.sh` was not run. Existing bookings have no deal rows, so drag, LOST, a locked bound deal, and the sales-exec move were not exercised in the browser.

### Git commands
Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add app/composables/useApi.ts tests/unit/useApi.test.ts
git commit -m "$(cat <<'EOF'
Keep the conflicting contact on a 409 so the panel can open Review merge.

The message parser remains the fallback when the field is absent.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-api
git add database/migrations/2026_09_22_200002_add_analytics_consent_version_to_business_rules.php
git commit -m "$(cat <<'EOF'
Let the analytics consent migration republish a pre-Sprint-10 rules document.

Pipeline and privacy defaults are filled when the published document lacks them.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/assets/css/crm.css \
  app/components/crm/ContactDrawer.vue \
  app/components/crm/DealDrawer.vue \
  app/components/crm/LogActivityModal.vue \
  app/components/crm/NewDealModal.vue \
  app/components/crm/NewTaskModal.vue \
  app/components/crm/contactHelpers.ts \
  app/components/crm/salesHelpers.ts \
  app/pages/crm/engine/activity.vue \
  app/pages/crm/sales/pipeline.vue \
  app/pages/crm/sales/tasks.vue \
  app/types/api.ts \
  eslint.config.mjs \
  i18n/locales/en.json \
  tests/unit/crmContactHelpers.test.ts \
  tests/unit/crmSalesHelpers.test.ts
git commit -m "$(cat <<'EOF'
Show the sales pipeline and the task queue from the API.

Stage, cash, and SLA stay on the server. A task completion never writes a booking.
EOF
)"
```

## Task 09 · anakata-panel · Consent and data rights

### Page
`/crm/system/consent` replaces the placeholder. The notice says consent is a CRM record checked at send time, and that the LOPDP / GDPR architecture is pending (LEG-002). The register and personal-data map render the API rows in order. Transactional is first. “Never” is the coral pill. Retention text and the rule key come from the API. Subject requests (list, filters, new request, drawer) render only when the user has `privacy.manage`. Everyone else sees one line: subject requests are handled by an Admin. The pending-items hint names LEG-001, LEG-002, LEG-004, TEC-003 and TEC-005. The screen does not load the issuer bank rule, so it does not add a separate LEG-004 sentence.

The request drawer asks for `verified_how` before complete, reject, or erase. Access builds the export and downloads the zip without showing its contents. Rectification links to the contact. Objection states that completing withdraws marketing, profiling and remarketing, and that transactional continues. Erasure asks for the contact email and shows the API outcome or the 409 sentence.

### Contact drawer
Transactional stays ALWAYS ON. Each register purpose shows OPTED IN, NOT OPTED IN, or NO RECORD from `GET /api/crm/contacts/{id}/consents`. History lists purpose, granted or withdrawn, version, when, capture point, who, how obtained, and the IP as “recorded” or “—”. Record consent is shown with `consents.record`. Purpose, granted or withdrawn, and how obtained are required. Text version is optional so a purpose with no published version (WhatsApp) can still be recorded; a blank version shows the API 422. ALL SENDS IN ENGLISH stays.

Helpers, tested: `consentStateLabel`, `subjectRequestDueClass`. The due class uses an overdue flag when the payload has one. This API does not send one, so an open request compares `due_at` with now.

### Checks
Panel `pnpm lint`, `pnpm typecheck`, `pnpm test` (255), and `pnpm build` passed. The overlay clone at `/tmp/anakata-fresh` (anakata-ui 0.11.0) typechecked and built the panel.

`reset.sh` was not applied to the dev database. The script targets compose project `anakata-e2e`, and that stack has no app container. The live `anakata-api` stack was left running.

Browser, both themes, on that stack:

- Dark, Carolina (Admin): register and data map, including Never in coral, and the subject-request list.
- Light, Mateo (Manager): the same register, and the subject-requests panel replaced by “Subject requests are handled by an Admin.” `GET /api/privacy/requests` as Mateo is 403. The register still loads.
- An engine pay-later request with marketing, after a `page_view` on the same session (`sprint10-1790066958@anakata.test`, contact 23, ANK-R-2026-0043): Marketing OPTED IN, capture point ENGINE_FORM, IP recorded. Analytics OPTED IN, capture point ENGINE_BANNER, at the first event time. Register marketing went from 9 to 10, then back to 9 after the objection. Analytics is 1.
- WhatsApp recorded as staff (version `staff-noted`, how obtained “Asked on the phone”): OPTED IN, capture point STAFF. Register WhatsApp is 1.
- Objection completed: marketing, profiling and remarketing are NOT OPTED IN with capture point SUBJECT_REQUEST. Transactional stays ALWAYS ON.
- Access export for contact 23 downloaded as `access.zip`. `access.json` has no passport, medical, dietary, nationality, or date-of-birth fields. The first build failed because `storage/app/private/privacy` was root-owned and mode 700; `chown` to the application user let the existing action write the zip. No application code change.
- Erasure of contact 23 (departure 2027-11-07) returned 409: “This contact has a booking whose return date has not passed.”
- Erasure of seed contact 19 (Anna Whitfield, no booking) completed. Contacts shows “Erased contact #19”. The outcome sentence includes the live 24-month passport window.

### Git
Not run.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/pages/crm/system/consent.vue \
  app/components/crm/ContactDrawer.vue \
  app/components/crm/NewSubjectRequestModal.vue \
  app/components/crm/SubjectRequestDrawer.vue \
  app/components/crm/privacyHelpers.ts \
  app/types/api.ts \
  i18n/locales/en.json \
  tests/unit/crmPrivacyHelpers.test.ts
git commit -m "$(cat <<'EOF'
Show the consent register, data map, and subject requests.

The contact drawer reads consent state from the API. Subject requests stay with an Admin.
EOF
)"
```

## Task 10 · anakata-panel · Campaigns, delivery, navigation

### Campaigns
`/crm/marketing/campaigns` replaces the placeholder. The notice says offers are created in the RMS and the CRM only measures bookings that carry the offer code or the campaign UTM key. Cards show the API measures. Null sends, clicks and ROAS render as "—". The sends note ("Marketing email is not built yet") is shown once. Bookings behind a card link to `/rms/reservations/bookings?open={reference}`. Offers without an active campaign can be wrapped with Create campaign when the user has `campaigns.manage` (name, preselected offer, UTM key, audience, media spend). Edit and archive sit on an active card. The attribution table and the conflict sentence come from `GET /api/crm/campaigns/attribution-model`.

The window line shows the booking window and the travel window from the offer, then the channel and PUBLISHED IN RMS. OPENING-27 has a null booking window and travel 2027-11-01 – 2027-12-31, so the line is `— · 2027-11-01 – 2027-12-31 · D2C`.

### Documents & Delivery
`/crm/sales/documents` shows the KPI row and the engagement note ("Opens and downloads are not tracked (LEG-002)"). The log lists booking, client name, document with RMS-rendered, version, channel, status and time, the error or blocked reason, who triggered it, and Open in RMS (`rms_path`). Filters are status, kind, dates and booking reference. There is no resend and no recipient address. The issuer bank rule is not loaded on this screen, so there is no LEG-004 sentence. One hint: "The CRM does not render documents."

`/rms/operations/documents?booking={id}` opens that booking's panel on the Documents tab.

### Navigation
`NavItem.sprint` is `number | 'later'`. Inbox, B2B Partners, Journeys, Segments and Automations say "Planned for a later sprint". Alerts stays "Coming in Sprint 11".

Helpers, tested: `deliveryStatusClass` (FAILED and BLOCKED → `bad`, SENT → `ok`), `formatMeasure` (null → "—").

### Checks
Panel `pnpm lint`, `pnpm test` (257), `pnpm typecheck`, and `pnpm build` passed again after the travel-window line. The overlay clone at `/tmp/anakata-fresh` (anakata-ui 0.11.0) typechecked after that line was copied in.

`reset.sh` was not applied. It targets compose project `anakata-e2e`, which has no app container. The live `anakata-api` database was used. The engine on port 3000 was down, so the UTM booking was `POST /api/engine/checkout`.

Browser, both themes, Mateo (Manager) unless noted:

- Light: create **Opening 2027** on OPENING-27, UTM `opening27`, audience "Opening guests". Seeded CONFIRMED bookings do not carry OPENING-27. The three rows that do are REQUESTED, and measures count sold statuses only, so the card showed redeemed 0 and revenue USD 0. That matches the sold set.
- Engine pay-later on departure 1 cabin S4 with first and last touch `campaign: opening27` (ANK-R-2026-0044). Status stayed REQUESTED. Attributed first touch stayed 0.
- Carolina confirmed that request in the RMS (`REQUESTED` → `CONFIRMED`, ANK-2026-0022). The card then showed redeemed 1, revenue USD 26,600, first touch 1 · USD 26,600, last touch the same. The bookings row linked to the RMS drawer.
- Mateo sees the drawer and the own-records line; he cannot cancel a booking owned by Carolina. Carolina cancelled it (`CANCELLED`, reason "Sprint 10 campaign measure check"). The card dropped to redeemed 0, revenue USD 0, first touch 0. Confirmed in the dark theme after reload.
- Dark delivery log: sent today 0, failed 1, blocked 1, queued over 15 min 0. The failed row is ANK-2026-0018, "TransportException: mailbox unavailable". The blocked row is the same booking, "No email address for the client of record". Open in RMS opened `/rms/operations/documents?booking=11` on the Documents tab.
- Placeholders: inbox, B2B partners, journeys, segments and automations say "Planned for a later sprint". Alerts says "Coming in Sprint 11".

### Git
Not run. `i18n/locales/en.json` and `app/types/api.ts` also hold the task 09 additions if that commit has not been made yet.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/pages/crm/marketing/campaigns.vue \
  app/pages/crm/sales/documents.vue \
  app/pages/rms/operations/documents.vue \
  app/components/crm/CampaignModal.vue \
  app/components/crm/campaignHelpers.ts \
  app/components/shell/PlaceholderPage.vue \
  app/navigation/crm.ts \
  app/navigation/types.ts \
  app/types/api.ts \
  i18n/locales/en.json \
  tests/unit/crmCampaignHelpers.test.ts
git commit -m "$(cat <<'EOF'
Show campaign measures and the delivery log from the API.

Later navigation says so. The CRM does not create an offer or resend a document.
EOF
)"
```

## Task 11 · anakata-api · E2E scenarios; P1 not run

### Code gate
Local HEADs, not a fetch:

| Repo | SHA | Subject | Against origin/dev |
|---|---|---|---|
| anakata-api | `fe0861a` | Measure campaigns from sold bookings and list what the RMS already sent. | ahead 6, dirty |
| anakata-ui | `39575f4` | Regenerate API types for the Sprint 10 CRM and privacy responses. | dirty; local tag `v0.11.0` |
| anakata-panel | `c1eceea` | Pin the shared layer fallback to v0.11.0. | ahead 1, dirty |
| anakata-engine | `c7e4ca3` | Pin the shared layer fallback to v0.11.0. | ahead 1, clean |

The gate wants `origin/dev` checked out hard, ui at the pushed tag `v0.11.0`, and a descendant whose diff outside `tests/e2e/runs` is empty. None of that is true, and this agent does not fetch, reset, commit, or push. `up.sh` was not run. Run file: `tests/e2e/runs/2026-09-22-1249-sprint10-env.md`.

`composer check` after the release projection and the release-closes-task cases: 1107 passed (8239 assertions), Pint clean (1234 files), Larastan no errors.

### Scenarios
Written, not walked. P1 grows by PIPE-01–03, TASK-01–03, PRIV-01–04, CAMP-01, DLV-01. P2: PIPE-04, TASK-04, PRIV-05, CAMP-02.

Revisits: CRM-01 (lifecycle unchanged after the register), CRM-03 (consent block no longer says the register arrives in Sprint 10), CRM-05 and CRM-06 (a named activity row opens the contact by `contact_id`; an anonymous name is not a button), CRM-08 (Review merge uses `conflicting_contact.id`), BR-01 (flagged pending-client rows now include analytics, eight pipeline rows, and the subject-request SLA; counts stay 81 / 56 / 15 / 10 / 35).

PRIV-01 also requires the I6 MARKETING row and `source_consent_id`. TASK-02 names `commissions.override_cap` and `bookings.overdue_decision`.

`reference-values.md` drops the stale 72-row bullets. Marker split for the Sprint 10 fixture block: every line there is ⚠ UNVERIFIED (seeder, `BusinessRulesDocument` initial, or a non-reset browser). None of those figures was read off a `reset.sh` screen. Screen-read lines in that block: 0.

### Runs
Not run in this session. E2E belongs on a Cursor cloud agent opened with only `anakata-api` (one git remote), which is the machine task 11 names. This workspace has four remotes, so a cloud agent started here cannot pass that spawn, and this session does not run the scenarios.

Both runs stay NOT RUN. Every new P1 id is NOT RUN. The Sprints 1–9 P1 on `e2e/sprint-09` is still not attached. The code-gate note remains `tests/e2e/runs/2026-09-22-1249-sprint10-env.md`.

### Open questions
These are still with the client. The sprint shipped the defaults.

- Pipeline stage probabilities (5 / 15 / 35 / 55 / 80) and stage SLAs (4 business hours, 5 business days, 7 business days). PENDING CLIENT.
- Who handles subject requests, and whether 30 calendar days is the LOPDP SLA. Default: Admin only, 30 days.
- Erasure while a return date is still ahead. Default: refuse until that date has passed.
- Where profiling, remarketing and WhatsApp consent are captured, and with what text. The engine captures marketing and, through the banner, analytics.
- Open and download tracking in transactional email. Not built.
- Journeys, segments, automations, the inbox and CRM B2B partners stay “later”.

### Merge steps
Not run. The sprint task files, the README, and section M in `docs/requirements/08-dev-decisions.md` are still untracked or modified, and no earlier section lists them:

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  docs/requirements/08-dev-decisions.md \
  docs/sprints/sprint-10
```

Commit the product work from tasks 01–10 first (the commands are in those sections), push `anakata-ui` tag `v0.11.0`, then the scenario branch. Run files are gitignored, so the run note needs `-f`:

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git checkout -b e2e/sprint-10
git add \
  tests/e2e/scenarios/INDEX.md \
  tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md \
  tests/e2e/scenarios/crm/CRM-01-seeded-contacts.md \
  tests/e2e/scenarios/crm/CRM-03-drawer-timeline-no-sensitive.md \
  tests/e2e/scenarios/crm/CRM-05-consent-stitch.md \
  tests/e2e/scenarios/crm/CRM-06-no-consent-no-events.md \
  tests/e2e/scenarios/crm/CRM-08-email-conflict-offers-merge.md \
  tests/e2e/scenarios/crm/PIPE-01-kpis-match-ledger-bound-locked.md \
  tests/e2e/scenarios/crm/PIPE-02-own-deal-move-lost-reason.md \
  tests/e2e/scenarios/crm/PIPE-03-request-then-deposit-confirmed.md \
  tests/e2e/scenarios/crm/PIPE-04-charter-enquiry-take-and-bind.md \
  tests/e2e/scenarios/crm/TASK-01-request-task-closes-on-release.md \
  tests/e2e/scenarios/crm/TASK-02-overdue-and-commission-cap.md \
  tests/e2e/scenarios/crm/TASK-03-complete-writes-timeline-not-booking.md \
  tests/e2e/scenarios/crm/TASK-04-manual-task-reassign-all-tab.md \
  tests/e2e/scenarios/crm/PRIV-01-engine-opt-in-on-register.md \
  tests/e2e/scenarios/crm/PRIV-02-objection-withdraws-marketing.md \
  tests/e2e/scenarios/crm/PRIV-03-access-export-no-passenger.md \
  tests/e2e/scenarios/crm/PRIV-04-erasure-after-return.md \
  tests/e2e/scenarios/crm/PRIV-05-manager-no-subject-requests.md \
  tests/e2e/scenarios/crm/CAMP-01-opening-27-redeemed.md \
  tests/e2e/scenarios/crm/CAMP-02-utm-first-touch.md \
  tests/e2e/scenarios/crm/DLV-01-log-matches-documents-tab.md \
  tests/e2e/fixtures/reference-values.md \
  docs/sprints/sprint-10/REPORT.md
git add -f tests/e2e/runs/2026-09-22-1249-sprint10-env.md
git commit -m "$(cat <<'EOF'
Add the Sprint 10 CRM scenarios and record the code-gate stop.

The P1 run waits until the four repos match the reviewed commits.
EOF
)"
```

After that push, and after `v0.11.0` is on the ui remote, the P1 run is a separate pass on `e2e/sprint-10`. It was not started here.

