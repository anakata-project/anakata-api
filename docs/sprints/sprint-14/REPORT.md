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
