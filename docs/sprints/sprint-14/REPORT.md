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
