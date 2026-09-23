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
