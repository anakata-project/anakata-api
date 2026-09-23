# Sprint 14 · Report

## Task 01 · Segments and suppression

Audiences are stored definitions. Membership is SQL, evaluated when it is asked for. Nothing stores a member list or a count.

### Schema

`segments`: `key` (unique), `name`, `sentence`, `conditions` (JSON), `dimensions` (JSON), `kind` (`MARKETING` or `OPERATIONAL`), `system`, `active`, `feeds`, timestamps and audit columns.

`feeds` is not in the task's column list. The list endpoint has to say what each definition feeds, and journeys do not exist until task 03, so the prototype's "Feeds →" line is stored on the row. Meta/Google and "audience push" are left out (Q10).

`DeliveryStatus::HardBounce` (`HARD_BOUNCE`) exists so suppression can name a real delivery outcome. The mailer does not write it yet. A hard bounce on a document plan is treated as a failed delivery so the status match stays complete.

System rows cannot be edited (409). There is no delete route. `segment` is on the history morph map. Creating or editing a custom definition writes `segment.created` or `segment.updated`.

### Condition vocabulary

A definition is `{ match: all|any, items: [{ field, operator, value, …params }] }`. One level. Unknown fields, operators or values are 422. `SegmentCompiler` turns each item into parameter-bound SQL. There is no raw-SQL field.

`GET /api/crm/segments/vocabulary` lists the fields, operators and allowed values (event names, booking statuses, lifecycles, LTV bands, consent states).

| Field | What it checks |
|---|---|
| `event_count` | Stitched `behavioural_events` for one event name, optional `within_days` |
| `festive_departure_views` | `view_departure` or `select_departure` whose `departure_id` is a festive departure |
| `booking_count` | Non-deleted bookings, optional `within_days` |
| `booking_status` | A non-deleted booking in the given statuses |
| `active_hold` | `REQUESTED` booking whose `booking_requests.hold_expired_at` is null |
| `lifecycle` | `ContactDerived::lifecycleSql()` |
| `ltv_band` | `ContactDerived::segmentSql()` (HIGH / MID / NEW from the current rules) |
| `nps` | `ContactDerived::npsSql()`; a missing score does not match |
| `consent` | Latest marketing row: `granted`, `withdrawn`, or `never` |
| `country` | ISO-2 on the contact |
| `guest_age` | A guest on the contact's booking whose age at that departure is in range. `dob` is used in SQL and never selected |
| `agency_id` | A booking's agency |
| `campaign` | Booking `utm_first` / `utm_last` campaign key, or the contact's own first/last touch |
| `last_activity_days` | Days since the later of the last stitched event and the last booking |
| `erasure` | A row in `erasure_log` |
| `hard_bounce` | A `HARD_BOUNCE` delivery on the contact's booking, or whose `to` contains the contact email |

Event counts use the raw event table only. The daily rollup has no contact (L6), so a contact's count falls away when raw events are rolled up.

### The nine definitions

Seeded by `SegmentsSeeder` (from the migration and `DatabaseSeeder`). `system` and `active` are true. Dimension tags stay as the prototype's labels even when they are not conditions. Marketing rows are AND-NOT suppression. Operational rows are not.

| Key | Kind | Sentence | Where it differs from the prototype |
|---|---|---|---|
| `warm_dreamers` | MARKETING | Viewed at least 2 itineraries and has not submitted a booking request. | Dropped "opened ≥ 3 emails" (opens are not tracked, M9). Wildlife/photography and US/UK stay tags only. |
| `abandoned_checkout` | MARKETING | Started checkout in the last 14 days and has not submitted a booking request. | Dropped "est. value > USD 25k" (event params are not a vocabulary fact). Anonymous events never match. |
| `holding_not_paid` | OPERATIONAL | Has a booking in REQUESTED whose hold has not expired. | Dropped "deposit link not clicked" (clicks are not recorded, M8). Operational so a withdrawn contact with a live hold still shows for the sales task. |
| `festive_prospects` | MARKETING | Viewed a festive departure. | Dropped festive-content clicks, OPENING-27 exposure and US East. The count is `festive_departure_views`, not a plain event count, because it joins the departure. |
| `families_6_17` | MARKETING | A guest on one of their bookings is aged 6 to 17 at departure. | Dropped family-content engagement and the child-rate FAQ. A browser with no booking does not match. |
| `past_guests_high_ltv` | MARKETING | Past guest whose lifetime value is in the HIGH band. | HIGH is the rules threshold (L2), not a copied 20000. NPS ≥ 8 stays a tag and is not a condition; the prototype sentence does not require it. |
| `advisors_non_producing` | OPERATIONAL | Approved travel advisor with no booking in the last 90 days. | Operational so "never gave marketing consent" does not empty the partner list. Task 03 still re-checks suppression when a partner journey sends. |
| `dach_luxury` | MARKETING | Country is Germany, Austria or Switzerland. | Dropped expedition engagement and "luxury traveller" (not stored). |
| `suppressed` | OPERATIONAL | Marketing consent withdrawn or never given, an erasure, or a hard bounce. | See suppression. |

### Suppression

`Suppression::applies(Contact)` and `Suppression::sql()` are the same four facts: marketing consent never given, marketing consent withdrawn, an `erasure_log` row, or a hard bounce. The `suppressed` definition compiles to that SQL.

Every `MARKETING` segment adds `AND NOT` that predicate. A marketing definition cannot opt out. The suppressed segment is operational, so it lists those contacts.

An unsubscribe is not its own store. Q7 (task 05) withdraws marketing consent, which this rule already matches.

### Endpoints

Under the existing CRM group (`panel.crm`, sensitive guard):

- `GET /api/crm/segments` — each definition, dimensions, feeds, live count. Cap **50** (an API safety constant). `meta.cap`, `meta.capped`, and a message when the cap bites. One `COUNT` per returned definition. System keys first, in prototype order, then other keys.
- `GET /api/crm/segments/{key}/contacts` — the same `WHERE`, paginated, `ContactResource` rows.
- `POST /api/crm/segments` and `PATCH /api/crm/segments/{key}` — `contacts.manage`. System rows are 409.
- `GET /api/crm/segments/vocabulary`

### Checks

`composer check` inside the app container: 1290 tests passed, Pint passed, Larastan passed.

### Deviations

- `feeds` column, as above.
- `festive_departure_views` is its own vocabulary field.
- `HARD_BOUNCE` was added to `DeliveryStatus`, and the document plan maps it to failed. The mailer is unchanged.
- `holding_not_paid`, `advisors_non_producing` and `suppressed` are `OPERATIONAL`.

### Open questions

None for this task. LEG-002 still blocks real marketing sends; this task does not send.

### Notes for later

- Task 03 re-checks `Suppression` at every journey step, on top of `ConsentGate`.
- Task 05's unsubscribe should withdraw marketing consent. That is enough to enter suppression. If it also needs its own marker, extend `Suppression` rather than a second rule.
- The mailer still records generic `FAILED`. Classifying a bounce as `HARD_BOUNCE` is what makes that arm of suppression true.
- Per-contact event counts only see raw events still retained (L6).
- `docs/requirements/08-dev-decisions.md` is already dirty in the working tree and this task did not edit it. The diff replaces Sprint 13 section P with Sprint 14 section Q. Do not stage it with the files below.

### Git

Not run:

```bash
git add \
  app/Actions/Crm/CreateSegment.php \
  app/Actions/Crm/UpdateSegment.php \
  app/Enums/DeliveryStatus.php \
  app/Enums/SegmentDimension.php \
  app/Enums/SegmentKind.php \
  app/Http/Controllers/Crm/SegmentController.php \
  app/Http/Requests/Crm/IndexSegmentContactsRequest.php \
  app/Http/Requests/Crm/StoreSegmentRequest.php \
  app/Http/Requests/Crm/UpdateSegmentRequest.php \
  app/Http/Resources/Crm/SegmentResource.php \
  app/Http/Resources/Crm/SegmentVocabularyResource.php \
  app/Models/Segment.php \
  app/Policies/SegmentPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Support/Crm/SegmentCompiler.php \
  app/Support/Crm/SegmentQuery.php \
  app/Support/Crm/SegmentVocabulary.php \
  app/Support/Crm/Suppression.php \
  app/Support/Documents/DocumentPlan.php \
  database/migrations/2026_09_23_150001_create_segments_table.php \
  database/seeders/DatabaseSeeder.php \
  database/seeders/SegmentsSeeder.php \
  docs/sprints/sprint-14 \
  routes/api/crm.php \
  tests/Feature/Crm/SegmentsTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php

git commit -m "$(cat <<'EOF'
Add CRM segments as SQL rules with suppression.

Audiences are definitions evaluated when asked for, and a suppressed contact is left out of every marketing segment.
EOF
)"
```

## Task 02 · The automation catalogue

One PHP list of every automatic message, and a switch that stops only the email. Flags, alerts, tasks, issued documents and report files keep happening. The panel list is task 09 and is not built here. Journeys and cart recovery stay `not_built`.

### Registry

`AutomationCatalogue` is code, not a table. Each `AutomationDefinition` has `key`, prototype section `a`–`g` and heading, `name`, `subject`, `trigger`, `timing`, `location` (a class, an `anakata:` command, or `alert:{AlertKind}`), `audience` (`customer` or `staff`), `kind` (`MARKETING` or `TRANSACTIONAL`), `switchable`, `locked_reason`, `built`, `not_built_note`, `alert_kind`, `journey_key`.

`journey_key` is null on every row. Task 03 fills it. Subjects for built rows are the real `DeliverySubject` or alert title. A not-built row keeps the prototype subject, has a note, and has no location.

**46 rows: 30 built, 16 not built.** The only `MARKETING` row is `review_request`. `ConsentGate` is unchanged and is still checked.

Switchable, because the record already exists and only the email stops:

| Key | What stays when it is off |
|---|---|
| `booking_confirmation`, `booking_summary` | The invoice and summary PDFs |
| `charter_proposal` | The issued proposal PDF |
| `balance_reminder_21`, `balance_reminder_7` | Independent slots on `anakata:documents-due` |
| `payment_receipt`, `final_invoice` | The receipt and the final invoice |
| `pretrip`, `questionnaire`, `voucher` | The issued document, or the questionnaire row |
| `survey` | The survey is transactional |
| `review_request` | Marketing. Consent is still required |
| `charter_enquiry` | The enquiry row, mail to the reservations mailbox |
| `report_email` | The report run file (O3) |

Not switchable. `locked_reason` is `The rule behind this message must not depend on a switch.` A settings row that says disabled is ignored:

- `portal_invite` — the plaintext token exists only in that job.
- `waitlist_offer` — `notified_at`, `waitlist.notified` and the follow-up task are the same transaction as the mail (O4).
- `data_chaser` — the chase is the rule (N5).
- One row per `AlertKind` (`alert:{value}`). Trigger and timing come from `AlertRegistry`. N1 emails only `CONFIRMED_AT_DEPARTURE`, `LEDGER_DRIFT` and `NPS_LOW`. The other kinds are inbox-only. Prototype names map onto kinds: overdue payment → `OVERDUE_BALANCE`, commission approval → `COMMISSION_CAP`, quote SLA → `SLA_BREACH`, low occupancy → `LOW_OCCUPANCY`, low NPS → `NPS_LOW`, sync failure → `DELIVERY_FAILED` (no bus, L5). Kinds the prototype does not list are still rows: wire, confirmed-at-departure, ledger drift, commission leakage, manifest data overdue, report failed, charter deposit due.

Not built, and not switchable, so a switch would not be a lie: welcome web lead and cart recovery 1–3 (task 05 / Q6); request acknowledgement (J7 sends on CONFIRMED); deposit link and wire instructions (staff send them from the RMS); overdue client email and the escalation email (no client mail; OPS-007 is a person); extras offer, extras closing, re-engagement, win-back (task 03); questionnaire reminder at T−14; arrival instructions at T−3; high-value new lead (no `AlertKind`).

### Switches

`automation_settings`: `key` unique, `enabled`, `disabled_reason`, `disabled_by`, `disabled_at`, timestamps, audit columns. No seed. A missing row means enabled. Morph key `automation_setting`.

`AutomationGate::allows(string $key)` is the only question a sender asks, once, immediately before `Mail::send`. Non-switchable keys always return true. A refused delivery-pipeline send sets the existing delivery to `BLOCKED` with `blocked_reason` equal to the disable reason, does not mail, does not retry, and does not dispatch `DeliveryOutcomeRecorded`. History event `automation.skipped` is written on the booking (or the enquiry, report run, alert, agency) with that reason, and `after.delivery_id` when there is a delivery. `AlertSweep` ignores a blocked delivery that has that history row, so a switch does not raise `DELIVERY_FAILED`. A blocked delivery with no skip history (no email address) still raises it. `FlagOverdueCommand`, `AlertSweep::raise` and `TaskSweep` do not call the gate. Re-enabling does not replay a skipped send; the idempotency key stays. A manual resend goes through `SendDeliveryJob` and hits the same gate. Payment-link and wire-instruction deliveries have no catalogue key, so the job allows them.

### Endpoints

On the existing CRM group (`panel.crm`, sensitive guard):

- `GET /api/crm/automations` — every catalogue row plus `enabled`, `disabled_reason`, `disabled_by` (name), `disabled_at`. Prototype section order, then key.
- `PATCH /api/crm/automations/{key}` — `enabled` (bool) and `reason` (required, max 1000). `rules.manage` via `AutomationSettingPolicy`. `UpdateAutomation` writes the row and one history entry (`automation.disabled` or `automation.enabled`) in one transaction.

Unknown key is 404. A key that is not built, or not switchable, is 422 with message `This message enforces a rule and cannot be switched off.` A missing reason is a validation 422.

### The overdue test

With `balance_reminder_21` switched off, the 21-day reminder delivery is `BLOCKED` with that reason, nothing is mailed, and `automation.disabled` plus `automation.skipped` carry the reason. `anakata:flag-overdue` still writes `booking.overdue_flagged`, the `OVERDUE_BALANCE` alert still raises, the overdue task still raises, and `anakata:alerts` does not raise `DELIVERY_FAILED`. The 7-day reminder still sends. Switching off `pretrip` still issues the itinerary document. A review request with the switch on and marketing consent withdrawn does not send; with the switch off and consent granted it does not send. A forced-off data chaser and a forced-off critical alert email still send. Patching `alert:OVERDUE_BALANCE`, `data_chaser` or `welcome_web_lead` returns the refusal sentence.

### Checks

`composer check` inside the app container: 1296 tests passed, Pint passed, Larastan passed.

### Deviations

`SendDocumentTest` calls `SendDeliveryJob::handle()` directly, so it now passes `AutomationGate`. The queue still injects the gate.

### Open questions

None for this task.

### Notes for later

- Task 03 points journeys at catalogue keys and can turn a not-built row into a built one. Until then those keys stay unswitchable.
- Task 05 owns welcome and cart recovery. Task 09 renders this list.
- A skipped send is not replayed when the switch is turned back on.

### Git

Not run:

```bash
git add \
  app/Actions/Charter/CreateCharterEnquiry.php \
  app/Actions/Charter/IssueCharterProposal.php \
  app/Actions/Crm/UpdateAutomation.php \
  app/Actions/Waitlist/OfferWaitlistEntry.php \
  app/Enums/AutomationAudience.php \
  app/Enums/AutomationKind.php \
  app/Http/Controllers/Crm/AutomationController.php \
  app/Http/Requests/Crm/UpdateAutomationRequest.php \
  app/Http/Resources/Crm/AutomationResource.php \
  app/Jobs/SendDeliveryJob.php \
  app/Jobs/SendPortalInviteMail.php \
  app/Models/AutomationSetting.php \
  app/Policies/AutomationSettingPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Support/Alerts/AlertMailer.php \
  app/Support/Alerts/AlertSweep.php \
  app/Support/Automations/AutomationCatalogue.php \
  app/Support/Automations/AutomationDefinition.php \
  app/Support/Automations/AutomationGate.php \
  app/Support/Automations/AutomationRow.php \
  app/Support/Reports/ReportMailer.php \
  database/migrations/2026_09_23_160001_create_automation_settings_table.php \
  docs/sprints/sprint-14/REPORT.md \
  routes/api/crm.php \
  tests/Feature/Crm/AutomationsTest.php \
  tests/Feature/Documents/SendDocumentTest.php

git commit -m "$(cat <<'EOF'
Add a catalogue of automatic messages and a switch that stops only the email.

A disabled message is blocked at the mail boundary, while flags, alerts, tasks and issued documents continue.
EOF
)"
```

## Task 03 · The journey engine

Enrol, wait, send, and exit live in the CRM. A journey writes enrolments, sends, deliveries, timeline rows, and handover tasks. It does not write a booking, a payment, a guest, or a document.

### Schema

One migration, seeded in `up()` and again from `DatabaseSeeder`. All eight journeys are `system` and `active = false`, so a deploy does not email anyone.

- `journeys`: key, name, goal, kind (`MARKETING` or `TRANSACTIONAL`), subject (`BOOKING` or `CONTACT`), trigger JSON (the list sentence is `trigger.line`), exit conditions, exit sentence, contract (null on marketing), active, system, audit columns.
- `journey_steps`: position, name, delay JSON, template key (a string; the template table is task 04), optional condition, action (`send`, `pointer`, or `task`), catalogue key, and `catalogue_keys` when one step points at more than one row.
- `journey_enrolments`: contact, optional booking, position, `next_due_at` (UTC), status (`ACTIVE`, `EXITED`, `SUPPRESSED`, `COMPLETED`), exit reason, enrolled at, exited at. `booking_subject` is `IFNULL(booking_id, 0)` and is unique with the journey and the contact. Contact-scoped journeys also refuse a second row in code, so a cruise re-engagement and a nurture-exhausted re-engagement cannot both exist for one person.
- `journey_sends`: step, template key, catalogue key, optional delivery, sent at.

Morph map: `journey`, `journey_enrolment`. History: `journey.enrolled`, `journey.exited`, `journey.suppressed`, `journey.completed`, `journey.updated`. `EnrolJourney` is the only insert. A second insert for the same subject returns the existing outcome and writes no second row.

The booking foreign key is `RESTRICT` rather than `SET NULL`. MySQL will not set a column null when a stored generated column is computed from it. Bookings are soft-deleted, so the restrict does not fire on the normal delete path.

### Calendar delays

Delays are calendar time. A day or a month is anchored on Galápagos midnight (`BusinessTime`). An hour is that many clock hours after the anchor instant. `next_due_at` is stored UTC.

An amount is an offset from an anchor (`enrolment`, `previous_step`, `departure`, `balance_due`, `reengagement`), not “N after the previous send” unless the anchor says so. Where the amount already exists as a rule, the due time reads `CurrentConfig::businessRules()`: `payments.balance_reminder_days` (21 and 7, before the balance due date), `payments.extras_due_hours` (72), `documents.pretrip_days_before` (45). Those numbers are not copied onto the step.

### The eight journeys

Counts on `GET /api/crm/journeys` are live `ACTIVE` enrolments at that position. Every response includes the suppression sentence. Marketing enrols only with marketing consent and no suppression. Transactional enrols anyway. The same check runs again before every step.

| Key | Kind | Pointers | Other steps |
|---|---|---|---|
| `nurture_to_request` | MARKETING | none | Day 0, 2, 6, 12, 21 sends from enrolment. Day 0 is `welcome_web_lead`. |
| `request_to_deposit` | TRANSACTIONAL | none | Hour 0 `request_acknowledgement`. Hour 4 handover task (the 24 h silence uses the same key, so it is raised once). Day 1 `deposit_link` does not open a Stripe session. Day 2 `hold_expiry_reminder`. |
| `payment_calendar` | TRANSACTIONAL | all three | `balance_reminder_21`, `balance_reminder_7`, then `alert:OVERDUE_BALANCE` the day after the balance is due. No second copy, no auto-cancel. `overdue_client` stays not built. |
| `extras_ancillaries` | TRANSACTIONAL | none | Day 7 `extras_offer`. T−60 `extras_second_window`. Closing send is `extras_due_hours` before departure. On board is a handover task, due at departure, and the exit waits until that task has run. |
| `ready_to_depart` | TRANSACTIONAL | the first three | Pre-trip points at `pretrip` and `questionnaire` (`pretrip_days_before`). The chase points at `data_chaser` on the manifest chase date (FIT T−25, charter T−40), not the prototype’s T−30. The alert points at `alert:MANIFEST_DATA_OVERDUE` on the DPNG due date (FIT T−15, charter T−30). That is not T−21. The 21 in `balance_reminder_days` is days before the balance due date. T−14 sends `questionnaire_reminder` only while a questionnaire is incomplete. T−3 sends `arrival_instructions`. The exit sentence still quotes the prototype’s “ops alert at T−21”. The NPS survey is not a step. |
| `reengagement` | MARKETING | none | Cruise completed, first send at return + 6 months, or nurture completed + 3 months. Month 6, 7, and 9. A HIGH LTV band raises one outreach task and sets `SUPPRESSED` (“HIGH-LTV personal outreach”) with no email. |
| `b2b_partner_activation` | TRANSACTIONAL | Day 0 `portal_invite` | Day 7 and day 21 send. The contact is the CRM contact whose email matches the agency. If there is none, nobody is created and nobody is enrolled. |
| `winback` | MARKETING | none | Day 1, day 30, month 6. Triggers: synchronous hold expiry, `CANCELLED` / `CANCELLED_POSTPAID`, or a deal stored as `LOST`. |

The quarterly B2B step never completes. After the handover task it stays on that position and sets `next_due_at` three calendar months on. Its task key is `journey-handover:{enrolment}:{step}:{Y-m-d}`, so the next quarter can raise again. Every other step uses `journey:{enrolment}:{step}` (tasks: `journey-handover:{enrolment}:{step}`), so a replay cannot send it twice.

### Re-check, and the hold listener

`anakata:journeys` runs every fifteen minutes (`*/15 * * * *`, `Pacific/Galapagos`, `withoutOverlapping`, `onOneServer`, `RecordScheduledRuns`). Each run enrols anyone the sweep still owes, applies time-based exits, then takes due `ACTIVE` enrolments. For each, in one transaction: exit, then `active`, then marketing consent and suppression, then the step condition, then pointer, task, or send. A failed marketing check sets `SUPPRESSED` and does not send. The same withdrawal does not stop a transactional step. Turning `active` off refuses a new enrolment and leaves a current one where it is, with no send. A catalogue switch that is off records `automation.skipped` once and does not advance a send; a pointer still advances. No usable address blocks the delivery and still advances (J6).

`SyncJourneys` is queued and runs after commit on `BookingCreated`, `BookingStatusChanged`, `PaymentSettled`, `AgencyApproved`, and `DealMarkedLost`. `SyncJourneysOnHoldExpired` is synchronous, registered after `MarkRequestHoldExpired`. `HoldExpired` is still dispatched inside the release transaction. A queued win-back listener could run before that commit.

`JourneyEnrolment::onLeadCaptured()` is public and has no caller. Task 05 calls it. Nurture also enrols from a stitched `abandon_cart`.

### Catalogue

Nine rows that were missing are now built, with location `journey:{key}`: `welcome_web_lead`, `request_acknowledgement`, `deposit_link`, `extras_offer`, `extras_closing`, `questionnaire_reminder`, `arrival_instructions`, `reengagement_6_months`, `winback`. Twelve new switchable customer rows were added. The catalogue is 58 rows, 51 built, 7 not built. A `journey:` location counts as resolved when that journey key is seeded. `keyForDelivery` resolves a journey delivery through `journey_sends`, so a switch flipped after the job is queued still blocks inside `SendDeliveryJob`. `JourneyMail` uses the step name as subject and body. Task 04 replaces that body.

### Tests

`tests/Feature/Crm/JourneysTest.php` covers the eight triggers (a second firing does not insert another row, including win-back’s three sources and B2B’s two), the wait / one delivery / complete path, consent withdrawal, the nurture exit before a due send, a disabled send switch versus a pointer, an inactive journey, the request handover raised once, and one runner pass that leaves `bookings`, `payments`, `guests`, and `documents` with the same ids and `updated_at`. `composer check` inside the app container: 1305 tests passed, Pint passed, Larastan passed.

### Open questions

None for this task. Which journeys are switched on at go-live is still LEG-002, which is why the seed leaves them inactive.

### Notes for later

- Task 04 replaces `JourneyMail` and refuses an unpublished template.
- Task 05 calls `onLeadCaptured` and must not send its own welcome. `welcome_web_lead` is the nurture day-0 send.
- Cart recovery (task 05) is a separate sequence from nurture. Both can see `abandon_cart`.
- “Re-enters nurture on engagement” is not built. Opens and clicks are not tracked (M9).
- The ready-to-depart step order matches today’s rule numbers (45, then chase, then DPNG, then T−14, then T−3). If those rules are edited so the chase falls after T−14, the fixed order will no longer be chronological.

### Git

Not run:

```bash
git add \
  app/Actions/Agencies/DecideAgency.php \
  app/Actions/Crm/EnrolJourney.php \
  app/Actions/Crm/MoveDealStage.php \
  app/Actions/Crm/UpdateJourney.php \
  app/Console/Commands/JourneysCommand.php \
  app/Enums/DeliveryKind.php \
  app/Enums/JourneyEnrolmentStatus.php \
  app/Enums/JourneyStepAction.php \
  app/Enums/JourneySubject.php \
  app/Enums/TaskKind.php \
  app/Events/AgencyApproved.php \
  app/Events/DealMarkedLost.php \
  app/Http/Controllers/Crm/JourneyController.php \
  app/Http/Requests/Crm/UpdateJourneyRequest.php \
  app/Http/Resources/Crm/CrmJourneyEnrolmentResource.php \
  app/Http/Resources/Crm/CrmJourneyResource.php \
  app/Jobs/SendDeliveryJob.php \
  app/Listeners/SyncJourneys.php \
  app/Listeners/SyncJourneysOnHoldExpired.php \
  app/Mail/Documents/DeliveryMailFactory.php \
  app/Mail/Documents/DocumentMail.php \
  app/Mail/Journeys/JourneyMail.php \
  app/Models/Journey.php \
  app/Models/JourneyEnrolment.php \
  app/Models/JourneySend.php \
  app/Models/JourneyStep.php \
  app/Policies/JourneyPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Support/Automations/AutomationCatalogue.php \
  app/Support/Crm/ContactTimeline.php \
  app/Support/Crm/EventCatalogue.php \
  app/Support/Documents/DeliverySubject.php \
  app/Support/Journeys/JourneyClock.php \
  app/Support/Journeys/JourneyEngine.php \
  app/Support/Schedule/AnakataSchedule.php \
  database/migrations/2026_09_23_170001_create_journeys_tables.php \
  database/seeders/DatabaseSeeder.php \
  database/seeders/JourneysSeeder.php \
  docs/sprints/sprint-14/REPORT.md \
  resources/views/mail/journeys/step.blade.php \
  routes/api/crm.php \
  tests/Feature/Crm/AutomationsTest.php \
  tests/Feature/Crm/JourneysTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/e2e/fixtures/reference-values.md

git commit -m "$(cat <<'EOF'
Add the CRM journey engine that enrols, waits, and sends without writing a booking.

Consent and suppression are checked again before every step, and the eight journeys stay inactive until someone turns them on.
EOF
)"
```

## Task 04 · Templates, preview and test sends

A journey send step now uses a published template version. The subject and body are no longer the step name. Pointer and task steps still do not render a template.

### Schema and immutability

`message_templates`: key, name, kind (`MARKETING` or `TRANSACTIONAL`, the journey’s kind), audit columns. `message_template_versions`: template, version (unique per template, from 1), subject, structured body, the variables it uses, `published`, `published_by`, `published_at`, `approval_reference`, audit columns. `template_test_sends`: version, staff user, the sample contact and optional booking, status, error, sent at, audit columns. Morph map key `message_template`.

A version is inserted once. The only later write is the publish transition, and only while `published` is still false: `published`, `published_by`, `published_at`, `approval_reference`, and the audit timestamps. After that the model throws, and a trigger refuses the update. The trigger also refuses any change to subject, body, variables, version, or template id, and refuses delete. Publishing version N+1 leaves version N. One open draft per template; a second draft is 409.

History, inside the action: `template.draft_created`, `template.published` (the approval reference is the reason), `template.test_sent`.

`journey_sends.template_version` was already on the table. A real send now writes the version it used. A send with no published version does not create a delivery or a `journey_send`, and the enrolment stays due.

### Variables

The server extracts tokens. The fixed list is `first_name` (the first word of `contacts.name`), `booking_reference`, `departure_date`, `itinerary_name`, `balance_due_date`, `deposit_link` (an open payment link, when one exists), `complete_link` (an active complete-page token, when one exists), and `unsubscribe_link`.

`unsubscribe_link` is `{engine_url}/unsubscribe/{hmac}` where the hmac is SHA-256 of the contact id with `APP_KEY`. No raw id and no email. The public page is task 05; this task only makes the variable resolve to a non-blank URL.

An unknown token is refused at publish and at preview. A known token with no value for that contact or booking is refused, never left blank. Publish cannot see a contact, so its check is that every token is in the list and that the kind rule holds.

### Unsubscribe rule

Publishing a marketing version without `{{unsubscribe_link}}` is refused: “A marketing template must include {{unsubscribe_link}}.” Publishing a transactional version that contains it is refused: “A transactional template must not include {{unsubscribe_link}}.” The kind must match the journey that uses the key. An approval reference is required.

### Preview and test send

`GET /api/crm/templates` lists each template with its published version and its open draft. `GET …/versions/{version}` reads one version. `POST …/drafts` creates the next version. `POST …/versions/{version}/publish` publishes it. `POST …/preview` with a contact or booking id returns the rendered subject and HTML and does not send or store. `POST …/test-send` uses the same inputs and mails only the signed-in user’s own address.

Reading, drafting, previewing, and test-sending need `panel.crm`. Publishing needs `rules.manage`, on the policy, the same split as automations. The route group already carries `panel.crm`; there is no extra middleware.

A test send is a `template_test_sends` row plus `template.test_sent`. It is not a `deliveries` row, so it never goes to the contact. Staff mail stays off the customer ledger.

Both kinds use one layout, the same shell as the transactional document mail (Anakata wordmark, Georgia, footer, reply-to). It does not extend that layout, because the document layout assumes a booking. The booking reference is added to the footer only when the sample has one. The old `mail/journeys/step` view, which printed the subject as the body, is removed.

A rendered welcome (it declares only `first_name` and `unsubscribe_link`) was checked against the report walk’s sentinel guest: passport, date of birth, medical, dietary and accessibility notes, guest email, survey text, and the guest’s name. Those strings are absent. The contact’s first name is present. `GB` is stored on that guest and is not one of the asserted strings, as in the report test.

### Seeded templates

Twenty-one send steps. Version 1 is inserted already published, approval reference `Sprint 14: initial journey template`. `welcome_web_lead` is one key on purpose: the journey seeder writes it into both `template_key` and `catalogue_key`. The catalogue key is what the automation switch gates. The template key is the words. The same pairing is every send step.

Nine subjects are the prototype `AUTOS` lines, with `[ID]` written as `{{booking_reference}}`:

| Key | Subject |
|---|---|
| `welcome_web_lead` | Your Galápagos adventure begins here — Anakata |
| `request_acknowledgement` | We have received your booking — {{booking_reference}} |
| `deposit_link` | Complete your reservation — {{booking_reference}} |
| `extras_offer` | Curated additions to your Galápagos expedition — Anakata |
| `extras_closing` | Last call for additions — {{booking_reference}} |
| `questionnaire_reminder` | 14 days to go — complete your questionnaire |
| `arrival_instructions` | Almost time! Final instructions for your arrival in San Cristóbal |
| `reengagement_6_months` | Back to Galápagos? A new expedition awaits you |
| `winback` | Sorry we missed you — what changed? |

These twelve have no `AUTOS` subject, so the subject is the step name: `nurture_story`, `nurture_itinerary`, `nurture_call`, `nurture_concierge`, `hold_expiry_reminder`, `extras_second_window`, `reengagement_month_7`, `reengagement_month_9`, `partner_positioning`, `partner_incentive`, `winback_day_30`, `winback_month_6`.

Every body is one placeholder paragraph. Marketing versions add an unsubscribe call to action. None of the seeded copy uses `deposit_link` or `complete_link`, because those URLs are often absent and a blank is forbidden; both stay in the fixed list for a later draft. All body copy, and the twelve subjects above, are pending the client (who writes the step text, and who approves a version before it sends).

### Tests

`tests/Feature/Crm/TemplatesTest.php`. `composer check` inside the app container: 1311 tests passed, Pint passed, Larastan passed.

### Open questions

None for this task. The step text and who approves it are still the sprint README’s question for the client. The seeded approval reference is the sprint line, not a client approval.

### Notes for later

- Task 05 owns the unsubscribe page. It should verify the same HMAC (SHA-256 of the contact id, `APP_KEY`) and must not put anything but that token on the URL.
- `deposit_link` and `complete_link` resolve only when the booking already has an open payment link or an active complete-page token. The deposit step still does not open a Stripe session.

### Git

Not run:

```bash
git add \
  app/Actions/Crm/CreateTemplateDraft.php \
  app/Actions/Crm/PublishTemplateVersion.php \
  app/Actions/Crm/SendTemplateTest.php \
  app/Enums/TemplateVariable.php \
  app/Http/Controllers/Crm/TemplateController.php \
  app/Http/Requests/Crm/PreviewTemplateRequest.php \
  app/Http/Requests/Crm/PublishTemplateVersionRequest.php \
  app/Http/Requests/Crm/StoreTemplateDraftRequest.php \
  app/Http/Resources/Crm/CrmMessageTemplateResource.php \
  app/Http/Resources/Crm/CrmMessageTemplateVersionResource.php \
  app/Mail/Journeys/JourneyMail.php \
  app/Mail/Templates/TemplateTestMail.php \
  app/Models/MessageTemplate.php \
  app/Models/MessageTemplateVersion.php \
  app/Models/TemplateTestSend.php \
  app/Policies/MessageTemplatePolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Support/Journeys/JourneyEngine.php \
  app/Support/Templates/RenderedTemplate.php \
  app/Support/Templates/TemplateRenderer.php \
  app/Support/Templates/TemplateTokens.php \
  app/Support/Templates/TemplateVariableException.php \
  app/Support/Templates/UnsubscribeLink.php \
  database/migrations/2026_09_23_180001_create_message_templates_tables.php \
  database/seeders/DatabaseSeeder.php \
  database/seeders/MessageTemplatesSeeder.php \
  docs/sprints/sprint-14/REPORT.md \
  resources/views/mail/journeys/step.blade.php \
  resources/views/mail/templates/message.blade.php \
  routes/api/crm.php \
  tests/Feature/Crm/TemplatesTest.php

git commit -m "$(cat <<'EOF'
Version the words a journey sends, and keep a test send off the customer.

A published template is immutable, marketing copy must carry an unsubscribe link, and a test goes only to the staff user who asked for it.
EOF
)"
```

## Task 05 · Lead capture, unsubscribe and cart recovery

A checkout tick keeps an address. One click on the unsubscribe link withdraws marketing consent. Cart recovery is a second branch of the existing nurture journey, not a new journey and not the `abandoned_checkout` segment.

### Checkout marketing version

`legal.consent_versions.checkout_marketing` defaults to `v1 (pending LEG-002)`. The sentence itself stays pending LEG-002; the version string is the consent text id the engine tick must send. The feed already emits `consentVersions->toArray()`, so the key appears once the document has it.

The registry row is `consent-checkout-marketing` (LEG-002, pending client, used in the engine checkout marketing tick). Fresh-seed counts are now tracked **91**, adjusted here **66**, other tabs **15**, locked **10**, differs / flagged **42**. `BR-01` and `tests/e2e/fixtures/reference-values.md` match that.

### Lead capture

`POST /api/engine/marketing-leads` (`throttle:engine-checkout`). Body: `email`, `first_name`, `consent` (must be true), `version`, optional `session_id`. `CaptureMarketingLead` writes nothing unless consent is accepted and `version` equals the published `checkout_marketing`.

It resolves the contact, records MARKETING granted at capture point `ENGINE_FORM` with that version and the request IP, stitches the session when `session_id` is present, and enrols the nurture `lead` branch. The stitch is unchanged: it still records ANALYTICS granted at `ENGINE_BANNER` (M3). This call site is in addition to L7’s list (request, waitlist, charter enquiry, complete page). The action does not send the welcome.

The response is always `{ "accepted": true }` for a new or existing address. No contact id.

### Unsubscribe

`contacts.unsubscribe_token` stores `hash_hmac('sha256', contact id, APP_KEY)`, the same token `UnsubscribeLink` puts in a marketing template. The raw insert in `ResolveContact` does not fire model events and the id does not exist until the row does, so the token is written immediately after the row is loaded. Eloquent creates set it too. Existing rows are backfilled. The token does not expire. Lookup checks the column and `hash_equals` against a recomputed HMAC.

`GET` and `POST /api/engine/unsubscribe/{token}` sit with the other token routes (`throttle:engine-complete`, `noindex`). `PagePath` stores `/unsubscribe/{token}` as `/unsubscribe/[token]`.

GET returns only `{ "valid": true, "already_unsubscribed": bool }`. Q7’s last clause wins over the task file’s “first name at most”: the payload has no name, booking, or address. An unknown token is 404 `{ "message": "This link is not valid." }`.

POST is `UnsubscribeContact`. If the latest MARKETING row is already withdrawn, it returns `already_unsubscribed: true` and writes nothing. Otherwise it records MARKETING granted false, capture point `UNSUBSCRIBE`, version `checkout_marketing`, and exits every active marketing enrolment now with reason exactly `unsubscribed`. Suppression is that withdrawal. There is no second marker.

### Cart recovery

New migration, not an edit of task 03’s journeys migration. `journey_steps.branch` and `journey_enrolments.branch` default to `lead` and existing rows are backfilled to that. The step unique becomes `(journey_id, branch, position)`. The enrolment unique becomes `(journey_id, contact_id, booking_subject, branch)` with the explicit name `journey_enrolments_subject_branch_unique` (the default Laravel name would exceed MySQL’s 64 characters). `booking_subject` stays generated.

Those old uniques were also the indexes behind the `journey_id` foreign keys, so the migration drops and restores those foreign keys around the swap.

The walker and `EnrolJourney` stay inside one branch. `contactEnrolled` is per branch, so one person can hold the lead branch and the cart branch. `exitFor` still exits every active row for that journey and contact, so a booking exits both. CRM step counts are grouped by journey, branch and position.

`lead` keeps the five existing steps. `abandoned_checkout` is three marketing sends from enrolment: 24 hours, 48 hours, 7 days. Subjects are the catalogue lines (`Can we help you plan your Galápagos expedition?`, `Still dreaming of Galápagos? We are here to help.`, `Can we help plan your trip?`). Bodies are one pending-client paragraph plus `{{unsubscribe_link}}`, approval `Sprint 14: initial journey template`. Copy stays pending the client.

`cart_recovery_1/2/3` are built rows on `journey:nurture_to_request`. Catalogue total stays 58; built is 54; not built is 4.

Enrolment is not `SegmentQuery` on the `abandoned_checkout` segment. That segment is still `begin_checkout` (at least one, within 14 days) and `booking_count` 0 within 14 days. It was not changed. The cart branch uses task 03’s population: a stitched `abandon_cart`, a granted MARKETING row, and no booking. `onLeadCaptured` still enrols `lead` only. A stitch from waitlist, charter, or the complete page without the tick does not enrol the cart branch.

### Hard bounce

`SendDeliveryJob` classifies the exception inside `handle()`, before a retry. SMTP `5.1.1`, or the phrases user unknown, user-unknown, mailbox not found, recipient rejected, or “does not exist”, sets that delivery to `HARD_BOUNCE` and returns. A bare `550` without those phrases is rethrown and stays the retry-then-`FAILED` path.

The contact is the booking’s contact, or the normalised address in `to` when there is no booking. The first hard bounce for that contact writes one `contact.suppressed` history row, reason `HARD_BOUNCE`. A later hard bounce updates that delivery only. `Suppression::hardBounced` already treats any `HARD_BOUNCE` delivery as suppression.

### Erasure hash

`SegmentCompiler::erasure` and `Suppression::applies` now also match `SHA2(LOWER(TRIM(contacts.email)), 256) = erasure_log.email_sha256`. No bindings, so `Suppression::sql()` still refuses a compiled predicate that takes bindings. The `suppressed` segment is `Suppression::conditions()`, so it picks the hash up. A later `ResolveContact` on an erased address creates a new contact, and marketing enrolment and marketing segments refuse it.

Transactional journeys still enrol. `enrol()` checks suppression only when the journey kind is marketing (Q2).

### Deviations

- `database/migrations/2026_09_21_200035_add_consent_versions_to_business_rules.php` is a merged migration. `ConfigPublisher` validates the raw array before `fromArray`, and `AddConsentVersionsMigrationTest` rebuilds `legal` from that migration’s closed `$defaults`. `checkout_marketing` was added to that list and nowhere else in the file. Without it, a fresh rebuild of `legal` fails validation.
- `MessageTemplatesSeeder` accepts 21 or 24 send steps. Migration `2026_09_23_180001` runs it before the cart steps exist, so a hard count of 24 would fail a fresh migrate. The branch migration calls the seeder again after the three steps are inserted. A database that has finished migrating has 24 templates.

### Tests

`tests/Feature/Engine/MarketingLeadUnsubscribeTest.php`, the cart and erasure cases in `tests/Feature/Crm/JourneysTest.php`, the hard-bounce case in `tests/Feature/Documents/SendDocumentTest.php`, plus the config-verify, OpenAPI, catalogue, template-count and page-path updates.

`composer check` inside the app container: 1318 tests passed, Pint passed, Larastan passed.

`anakata:config-verify` failed on the app database before migrate (`business_rules v1: legal.consent_versions.checkout_marketing` required) and passed after (`business_rules v2: valid`).

### Open questions

None for this task. The checkout marketing sentence and the three cart bodies are still pending the client (LEG-002 / the sprint copy question).

### Notes for later

- The prototype’s CRM task above USD 15,000, and the sales-exec assignment at 48 hours, are not in this task.
- Task 07 reads `checkout_marketing` from the feed. The sentence is not written here.
- An erased address can still be enrolled on a transactional journey. That is Q2: suppression blocks marketing only.

### Git

Not run:

```bash
git add \
  app/Actions/Contacts/ResolveContact.php \
  app/Actions/Crm/EnrolJourney.php \
  app/Actions/Engine/CaptureMarketingLead.php \
  app/Actions/Engine/UnsubscribeContact.php \
  app/Enums/ConsentCapturePoint.php \
  app/Http/Controllers/Crm/JourneyController.php \
  app/Http/Controllers/Engine/MarketingLeadController.php \
  app/Http/Controllers/Engine/UnsubscribeController.php \
  app/Http/Requests/Engine/StoreMarketingLeadRequest.php \
  app/Http/Resources/Crm/CrmJourneyResource.php \
  app/Http/Resources/Engine/EngineSettingsResource.php \
  app/Http/Resources/Engine/MarketingLeadResource.php \
  app/Http/Resources/Engine/UnsubscribeResource.php \
  app/Jobs/SendDeliveryJob.php \
  app/Models/Contact.php \
  app/Models/JourneyEnrolment.php \
  app/Models/JourneyStep.php \
  app/Support/Automations/AutomationCatalogue.php \
  app/Support/BusinessRules/Registry.php \
  app/Support/Config/Documents/BusinessRulesDocument.php \
  app/Support/Config/Documents/ConsentVersions.php \
  app/Support/Crm/SegmentCompiler.php \
  app/Support/Crm/Suppression.php \
  app/Support/Deliveries/BounceClassifier.php \
  app/Support/Engine/PagePath.php \
  app/Support/Journeys/JourneyEngine.php \
  app/Support/Templates/UnsubscribeLink.php \
  database/migrations/2026_09_21_200035_add_consent_versions_to_business_rules.php \
  database/migrations/2026_09_23_190001_add_checkout_marketing_consent_version_to_business_rules.php \
  database/migrations/2026_09_23_190002_add_unsubscribe_token_to_contacts.php \
  database/migrations/2026_09_23_190003_add_journey_branches.php \
  database/seeders/JourneysSeeder.php \
  database/seeders/MessageTemplatesSeeder.php \
  docs/sprints/sprint-14/REPORT.md \
  routes/api/engine.php \
  tests/Feature/Config/AddCheckoutMarketingConsentVersionToBusinessRulesMigrationTest.php \
  tests/Feature/Config/BusinessRulesEndpointsTest.php \
  tests/Feature/Crm/AutomationsTest.php \
  tests/Feature/Crm/JourneysTest.php \
  tests/Feature/Crm/TemplatesTest.php \
  tests/Feature/Documents/SendDocumentTest.php \
  tests/Feature/Engine/EngineFeedTest.php \
  tests/Feature/Engine/MarketingLeadUnsubscribeTest.php \
  tests/Feature/OpenApi/EngineResponseSchemasTest.php \
  tests/Unit/Engine/PagePathTest.php \
  tests/e2e/fixtures/reference-values.md \
  tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md

git commit -m "$(cat <<'EOF'
Keep a checkout address only with the tick, and let one click withdraw it.

Cart recovery is a branch of nurture, a hard bounce suppresses once, and an erased address cannot be marketed again.
EOF
)"
```

## Task 06 · Regenerate types, release v0.15.0

Types only. The layer is `0.14.1` → `0.15.0` (the task text said `0.14.0`; that release already shipped, and `0.14.1` is the agency-user patch). `pnpm types:api` regenerated `app/types/api.d.ts` from `http://localhost:8000/docs/api.json`. That file was not edited by hand. `crm.ts` still imports only `components` and `operations` from `./api`. `engine.ts` still imports only engine schemas.

### Prelude

The seven resources already had their top-level keys. The prelude only typed the gaps, and replaced `@return array<string, mixed>` on the files already being edited so Scramble drops the extra `anyOf` arm that is an array of strings. A missing row throws `LogicException` instead of returning `[]`. JSON values are unchanged (backed enums encode as their string).

- Journey `steps` items are the step object (`position`, `branch`, `timing`, `name`, `template_key`, `catalogue_key`, `catalogue_keys`, `action`, `count`). Enrolment `sends` items are the send object (`sent_at`, `template_key`, `catalogue_key`, `delivery_id`). Scramble does not follow `array_map`, so each list is a `foreach` that calls a private method. `contact`, `booking`, and `step` on the enrolment were already objects and were not re-typed.
- Template `published` and `draft` return `CrmMessageTemplateVersionResource|null` instead of `resolve()`, so they `$ref` the version. Version `body` is `paragraphs`, `list`, and `cta`. A `strings()` helper is what made the paragraph and list items `string[]`.
- Segment condition items are `field`, `operator`, `value`, and `event` / `within_days` when those keys are present. `value` stays the heterogeneous union. Dimension `axis` is `SegmentDimension::from` inside a `foreach`, so the response points at the schema that already existed on the write requests. `kind` does the same for `SegmentKind`. Those two schemas were not created again.
- Vocabulary `data.fields` items use the `describe()` shape (`field`, `label`, `operators`, `value`, optional `values` and `params`). The vocabulary route's documented response is `SegmentVocabularyResource`.
- Automation's 20 keys were already on the object arm. The return type is that object. `audience()` and `kind()` return the enums.
- Named schemas that were missing, emitted by a method whose native return type is the enum: `AutomationKind`, `AutomationAudience`, `JourneyStepAction`, `JourneyEnrolmentStatus`. `AutomationKind` is also journey `kind` and template `kind`.
- Contact `segment` is `ContactSegment` via `@scramble-return`. The method still returns the SQL string. Booking embeds omit the derived column; returning the enum would have turned that empty string into `NEW`.
- A template test send's `status` is `AlertNotificationStatus` (`SENT` | `FAILED`), which was already a schema on `TemplateTestSend`. The plan named `DeliveryStatus`. That name is the document-delivery union in the layer (`QUEUED` | `SENT` | `FAILED` | `BLOCKED`), so a second schema was not created.

`alert_kind` stays `string | null`. `JourneySubject` is not a journey response field. `MarketingLeadResource` and `UnsubscribeResource` were not edited. There is no unsubscribe FormRequest.

`CrmResponseSchemasTest` and `EngineResponseSchemasTest`: 2 passed (566 assertions). Pint passed. Larastan is clean on the touched files.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 16729 | 17860 |
| `app/types/crm.ts` | 232 | 253 |
| `app/types/engine.ts` | 375 | 378 |
| `app/types/index.ts` | 411 | 435 |

### Schema → alias

| Alias | Source |
|---|---|
| `ContactSegment` | named `ContactSegment` (was the hand-written band `'HIGH' \| 'MID' \| 'NEW'`) |
| `Segment` | `CrmSegmentResource` |
| `SegmentCondition` | `Segment['conditions']['items'][number]` |
| `SegmentKind` / `SegmentDimension` | named schemas |
| `SegmentVocabulary` | `CrmSegmentVocabularyResource` |
| `SegmentInput` / `SegmentUpdate` | `StoreSegmentRequest` / `UpdateSegmentRequest` |
| `AutomationRow` | `CrmAutomationResource` |
| `AutomationKind` / `AutomationAudience` | named schemas |
| `AutomationSwitchInput` | `UpdateAutomationRequest` |
| `Journey` | `CrmJourneyResource` |
| `JourneyStep` | `Journey['steps'][number]` |
| `JourneyStepAction` | named schema |
| `JourneyEnrolment` | `CrmJourneyEnrolmentResource` |
| `JourneyEnrolmentStatus` | named schema |
| `JourneyUpdate` | `UpdateJourneyRequest` |
| `MessageTemplate` | `CrmMessageTemplateResource` |
| `MessageTemplateVersion` | `CrmMessageTemplateVersionResource` |
| `TemplateDraftInput` | `StoreTemplateDraftRequest` |
| `PublishTemplateInput` | `PublishTemplateVersionRequest` |
| `TemplatePreviewInput` | `PreviewTemplateRequest` |
| `MarketingLeadInput` | `StoreMarketingLeadRequest` (`engine.ts`) |
| `UnsubscribeView` | `UnsubscribeResource` (`engine.ts`) |

The panel re-export and `contactHelpers.ts` now import `ContactSegment`. `BookingSegment` is unchanged.

### Leftovers

Condition `value` did not collapse. The generated item is `number | boolean | string | string[] | number[]`. `event` and `within_days` are required on that item even when the JSON omits them. No second union was written.

`StoreSegmentRequest.conditions` and `UpdateSegmentRequest.conditions` are `string[]`. The tree is checked in `withValidator`, not as item rules. `SegmentInput` and `SegmentUpdate` point at those schemas.

`StoreMarketingLeadRequest.consent` is Laravel's `accepted` union (`"yes" | "on" | "1" | 1 | "true" | true`).

`POST /api/engine/unsubscribe/{token}` has no request body, so there is no `UnsubscribeInput`.

`UnsubscribeView.already_unsubscribed` is `string`. The resource PHPDoc is `bool`. The resource was left as it was.

### Checks

Layer `pnpm lint`, `pnpm typecheck`, `pnpm test` (35) and `pnpm build` passed. Panel, engine, and portal `pnpm typecheck` and `pnpm build` passed against the sibling layer.

Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine,anakata-portal}`, working trees overlaid (no `node_modules`). The ui clone is **0.15.0**. Panel, engine, and portal resolve the sibling layer, so they do not fetch `#v0.15.0`. `pnpm typecheck` and `pnpm build` passed in all four.

- ui / panel / engine / portal: typecheck pass
- ui / panel / engine / portal: build pass
- **OVERLAY CLONE OK**

The tag is not pushed. The after-push clone was not run. Repeat the clone after the commands below, checking out `anakata-ui` at `v0.15.0` with no overlay.

### Git commands

Do not run these in the agent. Explicit paths only. Run in this order.

```bash
# 1. anakata-api prelude
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Controllers/Crm/SegmentController.php \
  app/Http/Controllers/Crm/TemplateController.php \
  app/Http/Resources/Crm/AutomationResource.php \
  app/Http/Resources/Crm/ContactResource.php \
  app/Http/Resources/Crm/CrmJourneyEnrolmentResource.php \
  app/Http/Resources/Crm/CrmJourneyResource.php \
  app/Http/Resources/Crm/CrmMessageTemplateResource.php \
  app/Http/Resources/Crm/CrmMessageTemplateVersionResource.php \
  app/Http/Resources/Crm/SegmentResource.php \
  app/Http/Resources/Crm/SegmentVocabularyResource.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/Feature/OpenApi/EngineResponseSchemasTest.php \
  docs/sprints/sprint-14/REPORT.md
git commit -m "$(cat <<'EOF'
Type the Sprint 14 segment, journey, and template responses.

Scramble now names the nested step, send, and condition objects, and the audience enums.
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
  app/types/crm.ts \
  app/types/engine.ts \
  app/types/index.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for Sprint 14 audiences and journeys.

The contact band is ContactSegment. Segment is the audience definition.
EOF
)"
git tag v0.15.0
git push origin HEAD
git push origin v0.15.0
```

```bash
# 3. pin the panel, the engine, and the portal
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  nuxt.config.ts \
  README.md \
  app/types/api.ts \
  app/components/crm/contactHelpers.ts
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.15.0.

The contact band type is ContactSegment.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add nuxt.config.ts README.md
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.15.0.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-portal
git add nuxt.config.ts README.md
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.15.0.
EOF
)"
```

## Task 07 · Checkout marketing tick and unsubscribe page

Engine only. No API change, no layer edit. The pin stays `github:anakata-project/anakata-ui#v0.15.0`.

### Details tick

The submit checkbox is unchanged: `flow.marketing`, label `details.marketing`, sent as `marketing` on `POST /api/engine/checkout/{token}/submit`. A second box sits under the email field, bound to `flow.cartMarketing` (default `false`). Its label is `details.checkoutMarketing`: news and expedition ideas, stop any time from the link in those emails, unticking does not withdraw a request already sent, wording pending (LEG-002).

The version is `settings.legal.consent_versions.checkout_marketing`, read off the hand-written five-key `EngineSettings` with `'checkout_marketing' in versions` and `typeof value === 'string'`. No cast and no widening. A missing or non-string key does not post.

The post is `POST /api/engine/marketing-leads` with `fetch`, `keepalive: true`, `credentials: 'include'`, and JSON. It runs from successful `submit()`, from `back()`, and from `onHide` (`pagehide` and `visibilitychange`). Not on tick and not on keystroke. An in-flight flag is set synchronously before the await, because both hide events call `onHide`. `sessionStorage` `anakata-checkout-marketing-lead` is set only when the JSON is `{ accepted: true }`. Any other response clears the flag while the page is still alive. `session_id` is included only via `submitSessionId()` (analytics consent already accepted). `navigator.sendBeacon` is not used: it returns no body, and the posted flag has to follow `{ accepted: true }`.

`marketingLeadBody` returns nothing when the box is unticked, when this session is already posted, when first name or version is empty, or when the email fails the same pattern as the form. Unticking after a successful post sends nothing. There is no toast.

Ticking this box and the submit checkbox writes two register rows. They are different writes: this post is `ENGINE_FORM` through `marketing-leads`; the submit checkbox still rides only on checkout submit. The browser pass ticked only the new box, so the contact has one `ENGINE_FORM` row from that post.

### Unsubscribe page

`/unsubscribe/[token]` loads `GET /api/engine/unsubscribe/{token}`. A valid link that is not yet withdrawn shows one sentence and one button. The button `POST`s with no body. After that, and on a later visit when `already_unsubscribed` is set, the page shows the confirmation and the line that messages about an existing booking still arrive. No name, booking, or address. An unknown token (404) shows "This link is not valid." and does not echo the token. `already_unsubscribed` is `string` on `v0.15.0`; confirmation is `String(flag) === 'true'`. The page calls no `track()`.

`noindex` is `useHead` and `useSeoMeta`, the same as survey and complete. The route rule for `/unsubscribe/**` is only `cache-control: no-store, private`, matching the other token rules. The task text says the route rule includes `noindex`; those four rules do not set that header, so this one does not either.

### Privacy

`redactPagePath` collapses `/unsubscribe/…` to `/unsubscribe/[token]`, query and hash stripped. `queuePageView` returns without enqueueing that path. `useAnalyticsConsent` does not call `loadGtag` while the route is that path, and loads it after the route leaves when consent is already accepted.

### Tests

`app/utils/marketingLead.ts` and `app/utils/unsubscribePage.ts`. Unit tests: unticked, one body, second call, untick after a post; prompt / confirmed / unknown; path redaction; a `page_view` whose path is an unsubscribe URL stores `/unsubscribe/[token]`; `queuePageView` does not enqueue the token.

`pnpm test` (22 files, 84 tests), `pnpm lint`, `pnpm typecheck`, and `pnpm build` passed. Fresh clone of the engine against `anakata-ui` `v0.15.0` (`28bd60f`) typecheck and build printed `FRESH_ENGINE_OK`.

### Browser

Dark theme, details step, email `browser.tick@anakata.test`, first name Browser. The new box starts unticked, directly under Email, and the submit checkbox stays unticked. Filling the form while unticked produced no `marketing-leads` request. Ticking the new box and choosing Back to cabins posted once: access log `POST /api/engine/marketing-leads` 200 from the browser at 14:51:07. `sessionStorage` `anakata-checkout-marketing-lead` is `1`. Contact 23 has `MARKETING` / `granted` 1 / `v1 (pending LEG-002)` / `ENGINE_FORM`. No success toast.

Local `nurture_to_request.active` is 0, and `JourneyEngine::enrol` returns null for an inactive journey, so this pass has no enrolment row. The withdraw therefore had no marketing enrolment to exit.

`/unsubscribe/{token}` for that contact showed the prompt and the button. The click posted and replaced it with the confirmation and the transactional line, in the light theme (`html` class `light`). A reload showed the same confirmation and no button. A second `POST` returned `{ valid: true, already_unsubscribed: true }` and the consent count stayed 2. The withdraw row is `MARKETING` / `granted` 0 / `UNSUBSCRIBE` / the same version. Unknown token SSR is 200, `cache-control: no-store, private`, `robots` noindex, visible text "This link is not valid." The access log for the unsubscribe visit is the GET and the POST of that path, and no `/api/engine/events` line.

Returning to `/book/details` after Back no longer renders the form, because Back releases the hold. The details checkbox was checked in the dark theme only.

### Deviations

`noindex` is page meta, not a route-rule header. The nurture enrolment in the browser check did not appear because that journey is inactive in this database.

### Git commands

Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add \
  app/composables/useAnalyticsConsent.ts \
  app/composables/useBookingFlow.ts \
  app/pages/book/details.vue \
  app/pages/unsubscribe/[token].vue \
  app/types/api.ts \
  app/utils/engineQueue.ts \
  app/utils/marketingLead.ts \
  app/utils/pagePath.ts \
  app/utils/unsubscribePage.ts \
  i18n/locales/en.json \
  nuxt.config.ts \
  tests/unit/engineEvents.test.ts \
  tests/unit/engineQueue.test.ts \
  tests/unit/marketingLead.test.ts \
  tests/unit/pagePath.test.ts \
  tests/unit/unsubscribePage.test.ts
git commit -m "$(cat <<'EOF'
Ask for a checkout address once, and add a public unsubscribe page.

The tick posts on leave, and the token page sends no analytics.
EOF
)"
```

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-14/REPORT.md
git commit -m "$(cat <<'EOF'
Record the checkout marketing tick and the unsubscribe page.
EOF
)"
```

## Task 08 · CRM Journeys

Panel only. No API change and no `api.d.ts` edit. Types stay the `v0.15.0` aliases re-exported from `#anakata-ui/app/types`.

### Page

`/crm/marketing/journeys` replaces the CRM catch-all for that URL. The nav item is sprint 14. Segments and Automations stay `later`. `pageDecision` allows the path for `panel.crm`.

Eight cards, API order. Each card shows name, goal, kind pill, trigger, contract when it is set, steps grouped by branch (nurture shows `lead` and `abandoned_checkout`), timing, action, and the step's `count` as “N enrolled”. Footer is `exit_sentence` and `suppression_sentence`. No conversion rate.

The active checkbox is shown for `rules.manage`. Confirm quotes `contract` when it is non-empty, otherwise `trigger`. PATCH `{ active }` replaces that card. A step links a template only when `template_key` is in `GET /api/crm/templates`. Disabled catalogue switches match `step.catalogue_keys` against `GET /api/crm/automations` and link to `/crm/engine/automations`. They are not toggled from this page.

### Enrolments and the contact drawer

The enrolments drawer is the paginated list. The contact name goes to `/crm/sales/contacts?open={id}`. A booking reference goes to `/rms/reservations/bookings?open={reference}` only with `panel.rms`.

The contact drawer loads `GET /api/crm/contacts/{id}/journeys` when the profile opens. Each row shows the journey key, status, step name, `next_due_at`, `exit_reason`, and each send's `sent_at` and `template_key`. A template key links to `/crm/marketing/journeys?template={key}`.

`JourneyEnrolment.sends` on `v0.15.0` is `sent_at`, `template_key`, `catalogue_key`, `delivery_id`. There is no `template_version`. The drawer does not label the current published version as the version that was sent.

### Templates

The panel shows the published version and the open draft from the list. It does not walk older version numbers. Preview and test send use a contact from `GET /api/crm/contacts?q=` and an optional booking id. Preview HTML is a sandboxed iframe. A 422 shows the API message. Test send toasts the returned subject.

Create draft (`POST …/drafts`) and publish (`POST …/versions/{version}/publish` with `approval_reference`) are only when `can('rules.manage')`. Versions are immutable and a second open draft is 409, so the form creates a draft only when `draft` is null. An existing draft is read-only plus the publish form. The template body is behind `can('panel.crm')`.

### Checks

`pnpm test` (48 files, 289 tests), `pnpm lint`, `pnpm typecheck`, and `pnpm build` passed against the sibling layer.

Fresh clone of the panel with no sibling `anakata-ui` uses `github:anakata-project/anakata-ui#v0.15.0`. The `#anakata-ui` finder now matches a layer that contains `app/types/engine.ts`, because the GitHub extract path is `.c12/github_anakata_project_*` and does not contain the string `anakata-ui`. Generated `tsconfig` excludes `node_modules`, so the layer's `anakata-augment.d.ts` is not part of that compilation. `app/types/api-error-hook.d.ts` repeats the `anakata:api-error` hook so `useApi` typechecks. Fresh typecheck and build printed `FRESH_PANEL_OK`.

`reset.sh` was not run. It targets compose project `anakata-e2e`. The browser pass used the running `anakata` stack.

### Browser

Signed in as Carolina (Admin). Dark theme, then light: eight cards, nurture shows both branches, the first lead step read “1 enrolled” after a lead tick.

`POST /api/engine/marketing-leads` for `browser.journey@anakata.test` returned `{ accepted: true }` while nurture was on. After reload the welcome step count was 1. The enrolments drawer linked Journey to `/crm/sales/contacts?open=24`. The contact drawer Journeys section showed `nurture_to_request`, ACTIVE, step “Cart recovery 1”, due 23 Sep 2026, 09:30. That step name is what `GET /api/crm/contacts/24/journeys` returns. The journey list's count of 1 is on the lead welcome step, not on Cart recovery 1. The panel renders both payloads as they are.

Preview of `welcome_web_lead` against Anna Whitfield returned the subject “Your Galápagos adventure begins here — Anakata”. Test send arrived in Mailpit at 15:28:08Z to `carolina@anakata.test`. A draft (version 2) was created and published with approval reference `Sprint 14 browser check`. The published row is now version 2; the open draft is gone.

Turning nurture on quoted `engine: lead.captured · abandon_cart — marketing consent required`. Turning Request to Deposit on quoted `The booking request they submitted.` and was cancelled. Turning nurture off quoted the same trigger line. A second lead, `browser.journey.off@anakata.test`, created contact 25 and `GET /api/crm/contacts/25/journeys` returned `{ data: [] }`. Enrolments stayed at 1. Nurture was left inactive.

`pretrip` and `questionnaire` were disabled through the automations API so the Ready to Depart step could show both switches. The card showed “Catalogue switch off” for Pre-trip package and Preferences questionnaire, each linking to `/crm/engine/automations`, with reason “Task 08 browser check”. Both were set back to enabled with reason “Task 08 browser check restore”.

### Deviations

Drafts cannot be replaced. `CreateTemplateDraft` returns 409 when an open draft exists, and versions are immutable except for publish. The form creates a draft only when none is open.

### Notes for later

The nurture welcome step's `count` and the enrolment's current step disagreed after one lead tick (count on the lead welcome step, current step “Cart recovery 1”). Worth a look on the API side. Task 09 still owns Segments and Automations.

### Git commands

Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/assets/css/crm.css \
  app/components/crm/ContactDrawer.vue \
  app/components/crm/JourneyEnrolmentsDrawer.vue \
  app/components/crm/JourneyTemplatePanel.vue \
  app/components/crm/journeyHelpers.ts \
  app/navigation/crm.ts \
  app/pages/crm/marketing/journeys.vue \
  app/types/api-error-hook.d.ts \
  app/types/api.ts \
  eslint.config.mjs \
  i18n/locales/en.json \
  nuxt.config.ts \
  tests/unit/guards.test.ts \
  tests/unit/journeyHelpers.test.ts
git commit -m "$(cat <<'EOF'
Add the CRM journeys page, template panel, and contact enrolments.

Staff can read the eight journeys, confirm an active change, and publish a template draft.
EOF
)"
```

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-14/REPORT.md
git commit -m "$(cat <<'EOF'
Record the CRM journeys page.

EOF
)"
```

## Task 09 · Segments and Automations

Panel only. No API change and no `api.d.ts` edit. Types stay the `v0.15.0` aliases. `Segment`, `SegmentCondition`, `SegmentVocabulary`, `SegmentDimension`, `SegmentKind`, `SegmentInput`, `SegmentUpdate`, and `AutomationSwitchInput` are re-exported from the panel's `app/types/api.ts`.

### Segments

`/crm/marketing/segments` replaces the CRM catch-all. The nav item is sprint 14. `pageDecision` allows the path for `panel.crm`.

`GET /api/crm/segments` supplies every card, in API order, with `key === 'suppressed'` pinned last and a warn border. A card shows name, `count`, the italic sentence, dimension tags (`axis` plus `label`), and `feeds`. Dimension colour follows the prototype: BEHAVIOUR `.dim.b`, INTEREST `.dim.i`, LOCATION `.dim.l`, PROFILE and PROMOTION `.dim.p`. An unknown axis is a plain `.dim`. `meta.message` shows when `meta.capped` is true. The panel does not count contacts.

A card opens `GET /api/crm/segments/{key}/contacts` at `per_page` 50. A row loads `GET /api/crm/contacts/{id}` into the existing contact drawer. Type and lifecycle options for that drawer come from `GET /api/crm/contacts?per_page=1` `meta.filters`.

New and Edit show only with `contacts.manage`. Edit is hidden when `system` is true. The builder reads `GET /api/crm/segments/vocabulary` and does not name a field itself. Combinators, operators, values, and params come from that payload. `in` sends a list, `age_range` sends two integers, `boolean` sends a boolean, and an unknown value kind stays a text input. Create sends `name`, `sentence`, `feeds`, `kind`, `dimensions`, and optional `active`. The server makes the key.

`StoreSegmentRequest.conditions` and `UpdateSegmentRequest.conditions` are `string[]` on `v0.15.0`. The posted rule is `{ match, items }` built from the vocabulary row, with params omitted when that field does not list them. It is not cast through `SegmentInput` or `SegmentUpdate`, and it is not annotated as `Segment['conditions']`. There is no unsaved-count route. A dirty rule shows no number. After `POST` or `PATCH`, the returned `count` replaces the card.

### Automations

`/crm/engine/automations` replaces the catch-all. The nav item is sprint 14. `GET /api/crm/automations` stays in arrival order. Rows group by `section` as they arrive, and the heading is `section_label`. The panel does not list `a`–`g`.

Each row shows name, quoted subject, trigger, timing, location, audience, and kind. `built === false` is grey and shows `not_built_note`, with no toggle. The toggle renders only when `switchable` is true and `can('rules.manage')`. Confirm needs a reason, then `PATCH /api/crm/automations/{key}` with `AutomationSwitchInput`. A disabled row shows `disabled_reason`, `disabled_by`, and `disabled_at`. A non-switchable row shows `locked_reason` and no toggle.

`journey_key` links to `/crm/marketing/journeys#${journey_key}`. `alert_kind` links to `/crm/engine/alerts?kind=${alert_kind}`.

### Companion edits

The journeys card `<article>` now has `:id="journey.key"`, so the hash lands on the card.

`AlertsInbox` reads `route.query.kind` only after `loadKinds()` has filled the section list, and only when that value is in `sectionKinds`. Any other query value is ignored. The list request waits until that decision, so the first load is not unfiltered and then filtered.

### Checks

`pnpm test` (49 files, 295 tests), `pnpm lint`, `pnpm typecheck`, and `pnpm build` passed against the sibling layer.

Fresh clone of the panel with no sibling `anakata-ui` uses `github:anakata-project/anakata-ui#v0.15.0`. Fresh typecheck and build printed `FRESH_PANEL_OK`.

`reset.sh` was not run. It targets compose project `anakata-e2e`. The browser pass used the running `anakata` stack.

### Browser

Signed in as Carolina (Admin). Dark, then light.

Nine seeded cards, suppressed last. Holding — not paid showed 3, and its list said “3 contacts”. The first row opened the contact drawer for E. Harmon.

New segment, vocabulary field Lifecycle, operator `eq`, value `GUEST`. Save returned count 0. The card “Task 09 check” sits above Suppressed and has Edit. That row is still in the dev database. The page has no delete.

Automations rendered sections a–g from `section_label`. Four unbuilt rows (Wire instructions, Overdue — day 1, Escalation — manual review, High-value new lead) are grey, show `not_built_note`, and have no toggle. Welcome — partner approved shows `locked_reason` and no toggle. Welcome — web lead was turned off with reason “Task 09 browser check”; the row showed that reason and “Carolina M. · 23 Sep 2026, 10:03”. It was turned back on with reason “Task 09 check finished” and left enabled.

The nurture journey link opens `/crm/marketing/journeys#nurture_to_request` on the article `nurture_to_request` (“Nurture to Request — D2C”). The SLA_BREACH link opens `/crm/engine/alerts?kind=SLA_BREACH` with the kind select set to `SLA_BREACH`.

### Deviations

The posted condition document is a plain object beside `SegmentInput` / `SegmentUpdate`, because those aliases type `conditions` as `string[]`.

`AlertsInbox` loads kinds, applies a matching query kind, then loads the list. The previous boot started both requests together.

### Notes for later

“Task 09 check” (lifecycle equals GUEST, count 0) is a real segment on the dev database. Delete it by hand if it should not stay.

### Git commands

Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/assets/css/crm.css \
  app/components/alerts/AlertsInbox.vue \
  app/components/crm/SegmentContactsDrawer.vue \
  app/components/crm/SegmentRuleModal.vue \
  app/components/crm/audienceHelpers.ts \
  app/navigation/crm.ts \
  app/pages/crm/engine/automations.vue \
  app/pages/crm/marketing/journeys.vue \
  app/pages/crm/marketing/segments.vue \
  app/types/api.ts \
  eslint.config.mjs \
  i18n/locales/en.json \
  tests/unit/audienceHelpers.test.ts \
  tests/unit/guards.test.ts
git commit -m "$(cat <<'EOF'
Add the CRM segments and automations pages.

Staff can build a segment from the vocabulary and switch a catalogue message with a reason.
EOF
)"
```

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-14/REPORT.md
git commit -m "$(cat <<'EOF'
Record the CRM segments and automations pages.

EOF
)"
```
