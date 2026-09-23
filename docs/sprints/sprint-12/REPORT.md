# Sprint 12 · Report

## Task 01 · The metrics layer

One SQL layer, `App\Support\Metrics\CommercialMetrics`, computes every commercial figure for a shared Galápagos window and an optional scope (yacht, itinerary, channel group, agency). Nothing is stored. `GET /api/rms/metrics` (`panel.rms`) returns the figures with the window used and the definition sentence, date filter and exclusion from `MetricCatalogue`.

| Metric | Formula | Filters on | Excludes |
|---|---|---|---|
| Occupancy | Sold berths ÷ sellable berths, per departure and as that ratio over the window. Sellable = cabins that are not blocked. A charter on a booking-claim status counts as every cabin on the yacht. Sold otherwise follows an active booking claim, the same state the calendar calls sold. | Departure date | Blocked cabins; expired holds (they are free). No people. |
| RevPAB | Cruise revenue ÷ sellable berths. Cruise revenue is `bookings.total` on bookings that hold a sold berth. | Departure date | Extras and Galápagos fees. |
| ADR | Cruise revenue ÷ berths sold. Same cruise revenue as RevPAB. | Departure date | Extras and Galápagos fees. |
| Lead time | Average and median whole days from the Galápagos sale date (`created_at` at UTC−6) to the departure date. | Departure date | Bookings that do not hold a sold berth. |
| Channel mix | Sold bookings and cruise revenue by `channel_of_origin`. Channels in `CommissionScan::tradeChannels()` are grouped as Trade. | Departure date | Extras and fees. |
| Nationality mix | Guest counts by country code. | Departure date | Names, emails and every other personal field. |
| NPS | Average score and promoter / passive / detractor counts. A score below `nps.alert_below` is a detractor; a score at or above `nps.review_request_from` that is not a detractor is a promoter. | Response date (same instant bounds as the guest-experience view) | Free text and guest names. |
| Commissions | Blocked, earned, payable and paid from `Accrual::statusSql()`. Paid is the payout amount. Approved agencies only. | Departure date | Agencies that are not approved. |
| Cash | `PaymentsKpis::for` for the same actor, window and scope. Deposit share is deposit receipts as a whole percent of collected. | Payment date for collected and deposits; departure date for pending and overdue | Collected is settled deposit and balance only, matching Payments & Revenue. |

Equality, on a window that holds a cabin booking, a blocked commission and a full charter, and on an empty 2031 window: occupancy matches the calendar (a charter’s sold berths are the yacht); cash matches Payments & Revenue; payable, paid and earned match the agencies KPIs; NPS average and response count match the guest-experience view. Cruise revenue stayed on `bookings.total` when an extra was attached. The sensitive-field walk is empty, and a guest name and email are absent. After a warm-up request, adding a booking does not change the query count.

Files: `app/Support/Metrics/*`, `app/Http/Controllers/Rms/MetricsController.php`, `app/Http/Requests/Rms/MetricsIndexRequest.php`, `app/Http/Resources/Rms/MetricsResource.php`, `app/Support/Payments/PaymentsKpis.php` (optional scope), `app/Enums/ChannelOfOrigin.php`, `app/Support/Operations/CommissionScan.php`, `routes/api/rms.php`, `tests/Feature/Metrics/CommercialMetricsTest.php`.

Git (not run):

```bash
git add app/Support/Metrics app/Http/Controllers/Rms/MetricsController.php app/Http/Requests/Rms/MetricsIndexRequest.php app/Http/Resources/Rms/MetricsResource.php app/Support/Payments/PaymentsKpis.php app/Enums/ChannelOfOrigin.php app/Support/Operations/CommissionScan.php routes/api/rms.php tests/Feature/Metrics/CommercialMetricsTest.php docs/sprints/sprint-12/REPORT.md
git commit -m "Add the commercial metrics layer for the dashboard and reports."
```

## Task 02 · Reports: definitions, runs and downloads

Ten definitions in `ReportDefinitions`. A run stores the normalised parameters and the closed window, then a queued job writes the files. With the test queue set to sync, the create response is already READY.

| Key | Permission | Formats | Body |
|---|---|---|---|
| payments-received | payments.record | CSV, XLSX | Settled deposit and balance receipts by payment date. Booking reference, kind, method, amount, payment reference. |
| overdue | payments.record | CSV, XLSX | Confirmed and agency-hold bookings with a cruise balance past the due date, whose departure is in the window. |
| forecast-30-day | payments.record | CSV, XLSX | Open cruise balances whose due date is in the window. |
| revenue-monthly | payments.record | CSV, XLSX | Cruise revenue of sold bookings by departure month. Extras and fees excluded. |
| commissions-payable | payments.record | CSV, XLSX | Accrual rows the status SQL marks payable. Agency reference and name, booking reference, amount. |
| gateway-reconciliation | payments.record | CSV, XLSX | Card payments that carry a gateway id. The gateway id is the only external identifier. |
| commercial-summary | panel.rms | PDF | The task 01 metrics for the window. Nationality is a guest count by country code. |
| occupancy | panel.rms | CSV, XLSX | Sold and sellable berths by departure. A charter counts as the whole yacht. |
| pipeline-summary | panel.rms | PDF | Collected, pending and overdue from Payments & Revenue for the window, plus scheduled cash. |
| agency-report | agencies.manage | CSV, XLSX | Blocked, earned, payable and paid by approved agency. Company name and reference only. |

`GET /api/rms/reports` lists the registry and whether the caller holds that definition's permission, on top of `panel.rms`. `POST /api/rms/reports/{key}/runs` validates the same window and scope as the metrics endpoint, inserts a QUEUED row, and dispatches `GenerateReportJob` after the insert commits. `GET /api/rms/reports/runs` lists the caller's permitted definitions. `GET /api/rms/reports/runs/{run}/file/{format}` streams the file. `RecordReportDownload` writes `report.downloaded`. A purged run, or a missing path, is 404.

`report_runs` keeps `definition_key`, `parameters`, `window_from`, `window_to`, `requested_by`, `subscription_id` (nullable, no foreign key until task 03), status, error, rows, `generated_at`, one path per format, `purged_at`, and the audit columns. Triggers refuse delete. An update may change status, error, rows, `generated_at`, `updated_at` and `updated_by`. Identity columns stay put. A path may go from null to a value once, and from a value to null on purge. `purged_at` may be set once. Files live on the private `reports` disk (`storage/app/reports`), as `{id}/{key}.{format}`.

The same definition and parameters over a closed window produce the same CSV bytes. A second payments-received run matched the first byte for byte. The personal-data walk covered every format of every definition and failed the file (CSV plain, XLSX shared strings, PDF streams) on a passport, date of birth, guest email, medical, dietary and accessibility notes, survey text, guest name, and the agency contact name and email. A booking reference and a gateway id are present. Country-code guest counts stay in the commercial summary.

`reports.retention_days` is 90 (PENDING CLIENT, O2). The registry row is `report-retention`. Counts are 86 / 61 / 15 / 10 / 38. `anakata:config-verify` fails on a latest document missing the key and passes after the migration publishes it as System. `anakata:retention` deletes files whose `generated_at` is older than that many days, nulls the paths, sets `purged_at`, and leaves the row. A run inside the window is kept.

Scheduled cash on the pipeline summary is the same unscoped figure Payments & Revenue shows (`PaymentsKpis::scheduledIn()`). It is not limited to the window.

Files: `app/Support/Reports/*`, `app/Actions/Reports/*`, `app/Jobs/Reports/GenerateReportJob.php`, `app/Http/Controllers/Rms/ReportController.php`, `app/Http/Requests/Rms/StoreReportRunRequest.php`, `app/Http/Requests/Rms/IndexReportRunsRequest.php`, `app/Http/Resources/Rms/ReportRunResource.php`, `app/Models/ReportRun.php`, `app/Enums/ReportRunStatus.php`, `app/Enums/ReportFormat.php`, `app/Support/Config/Documents/ReportsRules.php`, `app/Support/Config/Documents/BusinessRulesDocument.php`, `app/Support/BusinessRules/Registry.php`, `app/Console/Commands/RetentionCommand.php`, `app/Providers/AppServiceProvider.php`, `config/filesystems.php`, `routes/api/rms.php`, `database/migrations/2026_09_23_120001_add_reports_retention_to_business_rules.php`, `database/migrations/2026_09_23_120002_create_report_runs_table.php`, `tests/Feature/Reports/ReportRunsTest.php`, `tests/Feature/Config/AddReportsRetentionToBusinessRulesMigrationTest.php`, `tests/Feature/Config/BusinessRulesEndpointsTest.php`, `tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md`, `tests/e2e/fixtures/reference-values.md`.

Git (not run):

```bash
git add app/Support/Reports app/Actions/Reports app/Jobs/Reports app/Http/Controllers/Rms/ReportController.php app/Http/Requests/Rms/StoreReportRunRequest.php app/Http/Requests/Rms/IndexReportRunsRequest.php app/Http/Resources/Rms/ReportRunResource.php app/Models/ReportRun.php app/Enums/ReportRunStatus.php app/Enums/ReportFormat.php app/Support/Config/Documents/ReportsRules.php app/Support/Config/Documents/BusinessRulesDocument.php app/Support/BusinessRules/Registry.php app/Console/Commands/RetentionCommand.php app/Providers/AppServiceProvider.php config/filesystems.php routes/api/rms.php database/migrations/2026_09_23_120001_add_reports_retention_to_business_rules.php database/migrations/2026_09_23_120002_create_report_runs_table.php tests/Feature/Reports/ReportRunsTest.php tests/Feature/Config/AddReportsRetentionToBusinessRulesMigrationTest.php tests/Feature/Config/BusinessRulesEndpointsTest.php tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md tests/e2e/fixtures/reference-values.md docs/sprints/sprint-12/REPORT.md
git commit -m "Add on-demand report definitions, stored runs and file downloads."
```

## Task 03 · Report schedules and subscriptions

Four subscriptions are seeded. Recipients are never stored. At send time they are the active users who hold the definition's permission. `anakata:reports-send` runs every fifteen minutes in Galápagos time. A subscription is due once today's clock has reached `send_at` and the cadence's day matches. A missed quarter-hour later the same day still sends. The period key is the Galápagos date, the Monday of the week, `YYYY-MM`, or `YYYY-Q`. A second pass for that subscription and period does not open another run and does not send again. A failed notification is retried once, then left on the run.

| Definition | Cadence | Clock | Day | Window |
|---|---|---|---|---|
| commercial-summary | DAILY | 08:00 | every day | yesterday |
| occupancy | WEEKLY | 09:00 | Monday | the previous Monday–Sunday |
| pipeline-summary | MONTHLY | 08:00 | the 1st | the previous month |
| agency-report | QUARTERLY | 08:00 | the 1st of Jan, Apr, Jul, Oct | the previous quarter |

Doc 06 gives 08:00 and Monday 09:00. It does not give a clock for the monthly or quarterly send, so those use 08:00. There is no separate financial-summary definition; the six finance reports stay on demand.

`report_subscriptions` is unique on definition and cadence. `report_runs.subscription_id` now references it. `report_run_notifications` mirrors the alert notification row: status, error, attempts, sent_at, unique per run and user. The file is attached when it is at or under `anakata.report_attachment_bytes` (7,340,032). Otherwise the mail is the paragraph and the panel link.

`GET /api/rms/reports/subscriptions` is `panel.rms`. `PATCH` and `POST …/run-now` also need `rules.manage`. A manual run stores `parameters.source` = `manual` and the caller's id. If that period was already generated, run-now does not generate a second one. A failed generation leaves the run FAILED and raises WARN `REPORT_FAILED` for `panel.rms`, base key `report:{definition}`. A later READY run of that definition resolves it.

Files: `app/Support/Reports/ReportWindow.php`, `ReportDispatch.php`, `ReportMailer.php`, `app/Mail/Reports/ReportMail.php`, `app/Console/Commands/ReportsSendCommand.php`, `app/Actions/Reports/UpdateReportSubscription.php`, `app/Http/Controllers/Rms/ReportSubscriptionController.php`, `app/Models/ReportSubscription.php`, `app/Models/ReportRunNotification.php`, `app/Enums/ReportCadence.php`, `app/Enums/ReportRunSource.php`, `app/Enums/AlertKind.php`, `app/Support/Alerts/AlertRegistry.php`, `app/Support/Schedule/AnakataSchedule.php`, `config/anakata.php`, `database/migrations/2026_09_23_120003_create_report_subscriptions.php`, `tests/Feature/Reports/ReportSchedulesTest.php`.

Git (not run):

```bash
git add app/Support/Reports app/Mail/Reports app/Console/Commands/ReportsSendCommand.php app/Actions/Reports app/Http/Controllers/Rms/ReportSubscriptionController.php app/Http/Requests/Rms/UpdateReportSubscriptionRequest.php app/Http/Resources/Rms/ReportSubscriptionResource.php app/Models/ReportSubscription.php app/Models/ReportRunNotification.php app/Models/ReportRun.php app/Enums/ReportCadence.php app/Enums/ReportRunSource.php app/Enums/AlertKind.php app/Support/Alerts/AlertRegistry.php app/Support/Schedule/AnakataSchedule.php app/Providers/AppServiceProvider.php config/anakata.php database/migrations/2026_09_23_120003_create_report_subscriptions.php resources/views/mail/reports routes/api/rms.php tests/Feature/Reports/ReportSchedulesTest.php tests/Feature/Alerts/AlertsTest.php docs/sprints/sprint-12/REPORT.md
git commit -m "Schedule the four summary reports and email whoever holds the permission."
```

## Task 04 · Waitlist automation

When a cabin is free in a category that still has an active, un-notified waitlist entry, the sweep offers the next entries in FIFO order (`created_at`, then id). Free cabins are `Availability`'s `suites_free` and `owner_free`. An entry that already has `notified_at` still occupies one of those free cabins, so one newly free cabin notifies the next person and a later round with no new free cabin sends nothing. The candidate query is the un-notified, un-removed rows that do not already have a `waitlist:{id}` delivery.

`OfferWaitlistCabins` is queued from `AvailabilityChanged`, `HoldExpired`, and `BookingStatusChanged` into a status that does not hold inventory (cancelled or released). `anakata:waitlist-notify` runs every fifteen minutes in Galápagos time for the cases no event covers. Replay of the event and a second sweep do not send again.

The message is delivery kind `WAITLIST_OFFER`, key `waitlist:{entry}`, email only. It names the yacht, the departure date and the cabin category, links to the engine itinerary with `?departure={id}`, and says cabins are first-come and nothing is held. There is no consent gate: the guest asked to be told when they joined the waitlist (M2). WhatsApp stays TEC-005. A contact with no email gets one blocked delivery with that key and is not marked notified, so the next person with an address can still be offered the cabin. `deliveries.booking_id` is nullable because a waitlist entry has no booking; identifying columns stay immutable.

Nothing is claimed, held or booked. The claim count and every booking status are unchanged by a notification round.

Staff `NotifyWaitlistEntry` is unchanged. The list payload adds `auto_notified` (`notified_at` set and `notified_by` null). Position is `ROW_NUMBER()` over the active entries of that departure and category. Removing an entry drops it from the queue.

A sent offer raises `WAITLIST_FOLLOW_UP` once, key `waitlist-follow-up:{entry}`, unassigned, needs `bookings.create`, due two business days after the notice on the existing business-day calendar. No new alert kind. The task closes when the entry is removed, when that contact later holds a booking on the departure, or when staff complete it by hand.

Files: `app/Support/Waitlist/WaitlistOffers.php`, `app/Support/Waitlist/WaitlistOfferCopy.php`, `app/Actions/Waitlist/OfferWaitlistEntry.php`, `app/Actions/Waitlist/RemoveWaitlistEntry.php`, `app/Listeners/OfferWaitlistCabins.php`, `app/Console/Commands/WaitlistNotifyCommand.php`, `app/Mail/Waitlist/WaitlistOfferMail.php`, `app/Enums/DeliveryKind.php`, `app/Enums/TaskKind.php`, `app/Http/Controllers/Rms/WaitlistController.php`, `app/Http/Resources/Rms/WaitlistEntryResource.php`, `app/Support/Schedule/AnakataSchedule.php`, `app/Providers/AppServiceProvider.php`, `database/migrations/2026_09_23_120004_nullable_delivery_booking_for_waitlist.php`, `tests/Feature/Bookings/WaitlistAutomationTest.php`.

Git (not run):

```bash
git add app/Support/Waitlist app/Actions/Waitlist app/Listeners/OfferWaitlistCabins.php app/Console/Commands/WaitlistNotifyCommand.php app/Mail/Waitlist app/Mail/Documents/DeliveryMailFactory.php app/Mail/Documents/DocumentMail.php app/Jobs/SendDeliveryJob.php app/Enums/DeliveryKind.php app/Enums/TaskKind.php app/Models/Delivery.php app/Support/Documents/DeliverySubject.php app/Http/Controllers/Rms/WaitlistController.php app/Http/Resources/Rms/WaitlistEntryResource.php app/Support/Schedule/AnakataSchedule.php app/Providers/AppServiceProvider.php database/migrations/2026_09_23_120004_nullable_delivery_booking_for_waitlist.php resources/views/mail/waitlist tests/Feature/Bookings/WaitlistAutomationTest.php docs/sprints/sprint-12/REPORT.md
git commit -m "Notify the waitlist when a cabin frees, without holding it."
```

## Task 05 · The charter lifecycle

Statuses are NEW, CONTACTED, QUOTED, ACCEPTED, DECLINED and CLOSED. NEW may go to CONTACTED or CLOSED. CONTACTED may go to QUOTED or CLOSED. QUOTED may go to DECLINED or CLOSED. DECLINED may go to CLOSED. ACCEPTED may go to CLOSED only while no booking exists; once a booking exists, nothing leaves ACCEPTED. CLOSED goes nowhere. DECLINED and CLOSED require a reason. Every move writes `charter_enquiry.status_changed`. Issuing a proposal from NEW or CONTACTED moves the enquiry to QUOTED on the same path.

Three business-rule fields ship with the document. `charter.deposit_business_days` is 5 (FIN-003, confirmed). `charter.proposal_valid_business_days` is 10 (PENDING CLIENT, O5). `cancellation.charter_bands` copies the cabin bands (PENDING CLIENT, O6). Registry rows are `charter-deposit-business-days`, `charter-proposal-valid-days` and `cancellation-charter-bands`. Counts are 89 / 64 / 15 / 10 / 40. `anakata:config-verify` fails on a latest document missing the keys and passes after the migration publishes one version as System. A document that already has the keys is left alone. `fromArray` without the keys reads 0 and an empty band list.

`IssueCharterProposal` writes an append-only document of kind `CHARTER_PROPOSAL`. The first version draws `CP-{year}-{0000}` from `ReferenceType::Proposal`. A re-issue keeps that number, requires a reason, and is the next version. The snapshot holds the yacht, the departure and return, the guests, the quoted lines (charter week, plus the festive supplement when the departure is festive), the deposit percent and amount from the rates table, the balance at T−`charterBalanceDays`, the charter cancellation bands as text, and the sentence that extras and Galápagos fees are not part of the price. `POST /api/rms/charter-enquiries/{enquiry}/proposal` needs `bookings.create`. The mail is delivery kind `CHARTER_PROPOSAL`, key `charter-proposal:{enquiry}:{version}`. The link is `{engine_url}/charter-proposal/{token}`. The token purpose is `CHARTER_PROPOSAL` and it expires at the end of the nth business day. An older token still opens.

Acceptance is a typed name and a terms tick. It is not an electronic signature (TEC-003). The consent row is document `CHARTER_PROPOSAL`, version `{number} v{version}`, source ENGINE, with the time and the request IP. The enquiry stores `accepted_at`, `accepted_name` and the booking id. The booking is created only then, through `CreateReservation` (one charter booking, every yacht cabin claimed, no cabin). The price is the proposal snapshot when a live re-quote would differ. A second accept is 409. An expired or superseded version is 410, “A new proposal is needed.” Decline records an optional reason and creates no booking. `ConsentDocument::checklist()` leaves the proposal off the guest declaration list, so the complete-reservation declarations stay the original five.

The deposit due date is the Galápagos date of acceptance plus `charter.deposit_business_days` business days. Accepting on Friday 2027-11-05 is due 2027-11-12. `anakata:charter-deposits` runs daily in Galápagos time. An unpaid PENDING_PAYMENT charter past that date raises `CHARTER_DEPOSIT` and WARN `CHARTER_DEPOSIT_DUE` once, key `charter-deposit:{booking}`, audience `bookings.overdue_decision`. A charter enquiry has no owner column, so the task owner is the user who issued the proposal. Both close when the deposit is settled or the booking leaves PENDING_PAYMENT. Nothing is cancelled.

A charter cancellation freezes `cancellation.charter_bands` and stores `band_source` `CHARTER`. A cabin cancellation freezes the cabin bands and stores `CABIN`. Rows written before this column stay null. The enquiry list adds the latest proposal version and state (sent, accepted, declined, expired), `sla_breached` from OPS-009 while the enquiry is still NEW or CONTACTED, and the booking reference.

`composer check`: 1217 tests passed, Pint passed 1469 files, then PHPStan. The one PHPStan finding (`array_values` on the consent checklist) is removed; `phpstan analyse app/Enums/ConsentDocument.php` is clean.

Files: `app/Actions/Charter/*`, `app/Support/Charter/CharterDepositClock.php`, `app/Console/Commands/CharterDepositsCommand.php`, `app/Mail/Charter/CharterProposalMail.php`, `app/Http/Controllers/Engine/CharterProposalController.php`, `app/Http/Controllers/Rms/CharterEnquiryController.php`, `app/Http/Requests/Rms/IssueCharterProposalRequest.php`, `app/Http/Resources/Rms/CharterEnquiryResource.php`, `app/Http/Resources/Rms/RefundRequestResource.php`, `app/Actions/Refunds/CreateRefundRequest.php`, `app/Enums/CharterEnquiryStatus.php`, `app/Enums/DocumentKind.php`, `app/Enums/DeliveryKind.php`, `app/Enums/ReferenceType.php`, `app/Enums/BookingAccessTokenPurpose.php`, `app/Enums/ConsentDocument.php`, `app/Enums/TaskKind.php`, `app/Enums/AlertKind.php`, `app/Support/Config/Documents/CharterRules.php`, `app/Support/Config/Documents/BusinessRulesDocument.php`, `app/Support/BusinessRules/Registry.php`, `app/Support/Alerts/AlertRegistry.php`, `app/Support/Schedule/AnakataSchedule.php`, `database/migrations/2026_09_23_120005_add_charter_rules_to_business_rules.php`, `database/migrations/2026_09_23_120006_charter_proposal_columns.php`, `tests/Feature/Charter/CharterLifecycleTest.php`, `tests/Feature/Config/AddCharterRulesToBusinessRulesMigrationTest.php`.

Git (not run):

```bash
git add app/Actions/Charter app/Support/Charter app/Console/Commands/CharterDepositsCommand.php app/Mail/Charter app/Http/Controllers/Engine/CharterProposalController.php app/Http/Controllers/Rms/CharterEnquiryController.php app/Http/Requests/Rms/IssueCharterProposalRequest.php app/Http/Requests/Rms/UpdateCharterEnquiryRequest.php app/Http/Resources/Rms/CharterEnquiryResource.php app/Http/Resources/Rms/RefundRequestResource.php app/Http/Resources/Rms/DocumentResource.php app/Actions/Refunds/CreateRefundRequest.php app/Actions/Consents app/Enums app/Models/Document.php app/Models/Booking.php app/Models/RefundRequest.php app/Models/CharterEnquiry.php app/Models/BookingAccessToken.php app/Mail/Documents/DeliveryMailFactory.php app/Mail/Documents/DocumentMail.php app/Support/Documents app/Support/Config/Documents app/Support/BusinessRules/Registry.php app/Support/Alerts/AlertRegistry.php app/Support/Schedule/AnakataSchedule.php app/Services/Documents/DocumentView.php routes/api/rms.php routes/api/engine.php database/migrations/2026_09_23_120005_add_charter_rules_to_business_rules.php database/migrations/2026_09_23_120006_charter_proposal_columns.php resources/views/documents/charter-proposal.blade.php resources/views/mail/charter tests/Feature/Charter tests/Feature/Config/AddCharterRulesToBusinessRulesMigrationTest.php tests/Feature/Config/BusinessRulesEndpointsTest.php tests/Feature/Alerts/AlertsTest.php tests/Feature/Engine/EngineWaitlistCharterTest.php tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md tests/e2e/fixtures/reference-values.md docs/sprints/sprint-12/REPORT.md
git commit -m "Issue a charter proposal and turn acceptance into a booking."
```

## Task 06 · Regenerate types, release v0.13.0

Types only. The layer is `0.12.1` → `0.13.0`. `pnpm types:api` regenerated `app/types/api.d.ts` from `http://localhost:8000/docs/api.json`. That file was not edited by hand. `crm.ts` does not import metrics or reports. `engine.ts` aliases come only from `/api/engine` schemas. No runtime list of report keys, cadences or statuses.

### Prelude
Responses that were still a raw JSON body or an untyped array now return a resource whose `@return` names the enum class, so Scramble emits a named schema. Boolean expressions are assigned to a `(bool)` local first, so they stay booleans.

- Metrics: `CommercialMetrics::present()` returns each figure plus its catalogue `definition` (`sentence`, `filters_on`, `excludes`). `MetricsResource` documents that shape.
- Reports: `ReportDefinitionResource` (permission is `Permission`, formats are `ReportFormat`). `ReportController::index` returns that collection. `ReportRunResource.status` is `ReportRunStatus`; `formats` is the list of `ReportFormat` for the files that exist. `ReportSubscriptionResource.cadence` is `ReportCadence`.
- Waitlist: `cabin_category` is `CabinCategory`. `notified.channel` is `PreferredChannel`. `auto_notified` is a boolean.
- Charter: `CharterEnquiryResource.status` is `CharterEnquiryStatus`. The latest proposal `state` is `CharterProposalState`. `sla_breached` is a boolean. The engine page is `CharterProposalViewResource`, `CharterProposalAcceptedResource` and `CharterProposalDeclinedResource`, with `AcceptCharterProposalRequest` and `DeclineCharterProposalRequest`.

`PanelResponseSchemasTest` and `EngineResponseSchemasTest`: 4 passed (1020 assertions).

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 14603 | 15418 |
| `app/types/metrics.ts` | — | 11 |
| `app/types/reports.ts` | — | 21 |
| `app/types/offers.ts` | 37 | 50 |
| `app/types/inventory.ts` | 247 | 253 |
| `app/types/engine.ts` | 371 | 375 |
| `app/types/index.ts` | 365 | 388 |

### Schema → alias

| Alias | Source |
|---|---|
| `CommercialMetrics` | `MetricsResource` |
| `MetricWindow` / `MetricScope` | `MetricsResource.window` / `.scope` |
| `MetricDefinition` | occupancy `definition` |
| `ReportDefinition` | `ReportDefinitionResource` |
| `ReportRun` | `ReportRunResource` |
| `ReportRunStatus` | named `ReportRunStatus` |
| `ReportCadence` | named `ReportCadence` |
| `ReportSubscription` | `ReportSubscriptionResource`, `window` overlaid |
| `RunReportInput` | `StoreReportRunRequest` |
| `UpdateSubscriptionInput` | `UpdateReportSubscriptionRequest` |
| `WaitlistNotice` | `auto_notified`, `position`, `notified` on `WaitlistEntryResource` |
| `CharterEnquiry` / `CharterEnquiryStatus` | `CharterEnquiryResource` / named enum (`offers.ts`) |
| `CharterProposal` | enquiry `proposal`, `valid_until` overlaid |
| `CharterProposalState` | named `CharterProposalState` |
| `CharterProposalView` | `CharterProposalViewResource` |
| `AcceptCharterProposalInput` | `AcceptCharterProposalRequest` |
| `DeclineCharterProposalInput` | `DeclineCharterProposalRequest` |

`WaitlistEntry` stays the bookings alias. The generated row already carries `auto_notified`, `position` and `CabinCategory`.

### Leftovers
`CharterProposal.valid_until` is `string | null`. Scramble types the snapshot lookup as `unknown`.

`ReportSubscription.window` is `string | null`. Scramble types the parameters lookup as `unknown`.

The report file download stays a stream. There is no JSON schema and no alias.

`WaitlistEntryResource.notified.channel` is `PreferredChannel | null`. The fallback ternary still widens to null. `WaitlistNotice` keeps that generated field.

### Checks
Layer lint, typecheck, test (35) and build passed. Panel and engine typecheck and build passed against the sibling layer.

Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}`, working trees overlaid (no `node_modules`). The ui clone is **0.13.0** and has **no** `app/types/nuxt.d.ts`. Panel and engine resolve the sibling layer, so they do not fetch `#v0.13.0`. `pnpm typecheck` and `pnpm build` passed in all three.

- ui / panel / engine: typecheck pass
- ui / panel / engine: build pass
- **OVERLAY CLONE OK**

The tag is not pushed. The after-push clone was not run. Repeat the clone after the commands below, checking out `anakata-ui` at `v0.13.0` with no overlay.

### Git commands
Do not run these in the agent. Explicit paths only. Run in this order.

Tasks 01–05 are still uncommitted. Leave these paths out of those `git add` lists. They belong in the prelude commit: `app/Support/Metrics/CommercialMetrics.php`, `app/Http/Resources/Rms/MetricsResource.php`, `app/Http/Controllers/Rms/ReportController.php`, `app/Http/Resources/Rms/ReportRunResource.php`, `app/Http/Resources/Rms/ReportSubscriptionResource.php`, `app/Http/Resources/Rms/WaitlistEntryResource.php`, `app/Http/Controllers/Engine/CharterProposalController.php`, `app/Http/Resources/Rms/CharterEnquiryResource.php`, `app/Enums/CharterProposalState.php` (do not let the task 05 `app/Enums` add sweep it in), both OpenAPI schema tests, and `docs/sprints/sprint-12/REPORT.md`.

```bash
# 1. anakata-api prelude
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Metrics/CommercialMetrics.php \
  app/Http/Resources/Rms/MetricsResource.php \
  app/Http/Controllers/Rms/ReportController.php \
  app/Http/Resources/Rms/ReportDefinitionResource.php \
  app/Http/Resources/Rms/ReportRunResource.php \
  app/Http/Resources/Rms/ReportSubscriptionResource.php \
  app/Http/Resources/Rms/WaitlistEntryResource.php \
  app/Http/Resources/Rms/CharterEnquiryResource.php \
  app/Http/Controllers/Engine/CharterProposalController.php \
  app/Http/Resources/Engine/CharterProposalViewResource.php \
  app/Http/Resources/Engine/CharterProposalAcceptedResource.php \
  app/Http/Resources/Engine/CharterProposalDeclinedResource.php \
  app/Http/Requests/Engine/AcceptCharterProposalRequest.php \
  app/Http/Requests/Engine/DeclineCharterProposalRequest.php \
  app/Enums/CharterProposalState.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  tests/Feature/OpenApi/EngineResponseSchemasTest.php \
  docs/sprints/sprint-12/REPORT.md
git commit -m "$(cat <<'EOF'
Type the Sprint 12 metrics, report, waitlist and charter responses.

Scramble now names the report, cadence and charter enums, and each figure carries its definition.
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
  app/types/metrics.ts \
  app/types/reports.ts \
  app/types/offers.ts \
  app/types/inventory.ts \
  app/types/engine.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for the Sprint 12 metrics, reports and charter page.

Aliases point at the generated schemas. The proposal expiry and the subscription window stay string overlays.
EOF
)"
git tag v0.13.0
git push origin HEAD
git push origin v0.13.0
```

```bash
# 3. pin the panel and the engine
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add nuxt.config.ts README.md
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.13.0.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add nuxt.config.ts README.md
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.13.0.
EOF
)"
```

## Task 07 · Commercial Dashboard

`/rms/commercial/dashboard` is the first Commercial item. It needs `panel.rms`. The page asks `GET /api/rms/metrics` once per filter change, 300 ms after the last change, and drops the previous payload when the filters move. The date range is the shared `DateRangeFilter`, opened on the current calendar year. Yacht, itinerary, channel group and agency are the other query fields.

The KPI row is occupancy, RevPAB, ADR, average lead time, NPS and commissions outstanding (`payable`). Cash is collected, pending, overdue and the deposit share, with the cash definition under that row. Occupancy by departure is a table: the date links to the calendar, the bar is the completeness bar, and a ratio below `alerts.low_occupancy_pct` is coral. That percent is read from the current business rules when the user has `rules.view`. Channel mix and nationality mix are the API rows in the API order (bookings and cruise revenue; country code and guest count). NPS repeats the average and the promoter, passive and detractor counts. Each figure's info line is the API definition sentence plus `filters_on` and `excludes`. A null ratio, a zero ratio and a null or zero deposit share render as an em dash.

The channel and nationality payloads have no share field. The tables do not derive one.

### Checks
Panel lint, typecheck, test (273) and build passed. Fresh clone at `/tmp/anakata-fresh` (sibling layer **0.13.0**) typechecked and built the panel.

`reset.sh` targets compose project `anakata-e2e`, which is not running. The same steps (`migrate:fresh --seed`, Redis flush, Mailpit clear, `anakata:config-verify`) ran on the `anakata` project the panel uses.

For 2026-01-01 to 2026-12-31, cash collected, pending and overdue equal Payments & Revenue: 103493, 0, 0. Occupancy is null (no departures in that year; the seed sails in 2027), so the occupancy figure is an em dash. A 2020 window is null occupancy, RevPAB, ADR, lead time, NPS and deposit share, and no departure rows. For 2027-11-07 scoped to ANAMARA, the single departure row is 2/9 and `0.2222`, the same as that day's occupancy total. Every metric carries a definition sentence.

The login page opened in the browser. Signing in was blocked before the password field could be filled, so the dashboard was not clicked through in either theme. The figure comparison above is the metrics payload the page renders.

Git (not run):

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/pages/rms/commercial/dashboard.vue \
  app/components/commercial/dashboardHelpers.ts \
  app/navigation/rms.ts \
  app/types/api.ts \
  app/assets/css/bookings.css \
  eslint.config.mjs \
  i18n/locales/en.json \
  tests/unit/dashboardHelpers.test.ts \
  tests/unit/guards.test.ts
git commit -m "$(cat <<'EOF'
Add the commercial dashboard from the metrics payload.

Every figure is the API value, and a zero occupancy ratio renders as an em dash.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-12/REPORT.md
git commit -m "$(cat <<'EOF'
Record the commercial dashboard.

EOF
)"
```

## Task 08 · Reports

`/rms/commercial/reports` sits under Commercial, after the dashboard, and needs `panel.rms`. The page loads definitions, runs and subscriptions together.

A definition shows its title, sentence and formats. Run appears only when `allowed` is true. The form posts the window and the optional yacht, itinerary, channel and agency. The new run is QUEUED until a refresh finds READY or FAILED. Polling is every second and stops when nothing is queued, and when the page is left.

The runs table shows the definition title, the window, Manual or Scheduled (`requested_by` null is Scheduled), the generated time, the row count and the status. A FAILED run shows `error`. A run with `purged_at` shows "File removed after {n} days", where `n` is `reports.retention_days` on the current business rules. With no rules document the line is "File removed." Downloads use `downloadDocumentFile` for each format the run still has. The panel does not render the file.

Subscriptions are visible to anyone who can open the page. Active, send time and Run now need `rules.manage`. The recipient line is "Everyone holding {permission}." using the definition's permission. The schedule line is the cadence and the Galápagos clock the API stores (weekday or day of month when the API sent one). Run now posts and the resulting run is Manual because `requested_by` is set.

### Checks
Panel lint, typecheck, test (276) and build passed. Fresh clone at `/tmp/anakata-fresh` typechecked and built the panel against sibling layer 0.13.0.

The browser was not signed in, so the daily payments CSV and XLSX, the commercial-summary PDF, Lucía's missing Run button, Run now, and a FAILED error were not clicked through. Those behaviours follow the payload: Run is bound to `allowed`, downloads to `formats`, and the error text to `error`.

### Deviations
The subscription resource has no next-run timestamp and no audience sentence. The page does not compute the next calendar moment. It shows the stored clock and builds the recipient sentence from the definition permission. The run resource has a user id and no name, so a manual run is labelled Manual.

Git (not run):

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/pages/rms/commercial/reports.vue \
  app/components/commercial/reportHelpers.ts \
  app/navigation/rms.ts \
  app/types/api.ts \
  i18n/locales/en.json \
  tests/unit/reportHelpers.test.ts \
  tests/unit/guards.test.ts
git commit -m "$(cat <<'EOF'
Add the reports page for runs, downloads and schedules.

Run appears only when the definition allows it, and a purged file uses the published retention days.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-12/REPORT.md
git commit -m "$(cat <<'EOF'
Record the reports page.

EOF
)"
```

## Task 09 · Charter lifecycle and the waitlist

Charter enquiries stay on Booking Requests. The list filters by the six statuses. A breached SLA is the API flag `sla_breached`. The proposal column is the latest version and state, and `valid_until` when the API sent a date. The booking reference opens that booking. The drawer shows the contact, dates, guests, message, status, proposal and, once accepted, the version, state and booking reference.

Actions follow `UpdateCharterEnquiryStatus`: Contacted from NEW; Issue from NEW, CONTACTED and QUOTED; Decline from QUOTED; Close from NEW, CONTACTED, QUOTED, DECLINED, and ACCEPTED only when no booking is linked. Decline and Close ask for a reason. Issue posts the enquiry id and, on a re-issue, the reason. The response is previewed with the document preview. Send posts that document. Buttons need `bookings.create`.

The waitlist already showed position. A notice with `auto_notified` reads as the system, with the time; a person notice still names `notified.by`. One line under the table says the system notifies the first entry when a cabin frees and that nothing is held. Notify now is unchanged.

### Checks
Panel lint, typecheck, test (278) and build passed. Fresh clone at `/tmp/anakata-fresh` typechecked and built the panel against sibling layer 0.13.0. The browser was not signed in, so the enquiry was not taken through QUOTED and the waitlist was not clicked after a cancellation.

### Deviations
The issue request accepts only a reason. The drawer does not collect week, guests, price, inclusions or notes. Those values are on the issued document, which the preview shows.

The enquiry payload has no document id, no history, no accepted name or time, and no deposit due date. Send and preview use the document returned by Issue in that session. There is no charter history route. The booking resource has no proposal version and no `deposit_due_on`, so the booking overview does not invent those rows. A missed deposit is not drawn, because the enquiry payload does not include the alert or the task.

Git (not run):

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/components/requests/CharterEnquiriesPanel.vue \
  app/components/requests/charterActions.ts \
  app/pages/rms/operations/holds.vue \
  i18n/locales/en.json \
  tests/unit/charterActions.test.ts
git commit -m "$(cat <<'EOF'
Show charter enquiry actions and how a waitlist entry was notified.

Actions follow the status the API allows, and a system notice is labelled as the system.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-12/REPORT.md
git commit -m "$(cat <<'EOF'
Record the charter enquiry drawer and the waitlist notice.

EOF
)"
```

## Task 10 · Charter proposal page

`/charter-proposal/[token]` loads `GET /api/engine/charter-proposal/{token}` and shows the HTML the API rendered, with the version, number, validity date and the price lines, total and deposit beside it. Accept posts the typed name and `terms: true`. The button stays disabled until the name is non-empty and the box is ticked, so that post is not sent. The result shows the booking reference and deposit due date from the accept response, and the deposit amount from the proposal price already loaded. Decline posts an optional reason and then the declined acknowledgement. A 409 or 410 shows the API message. An already accepted, declined or expired proposal hides the form; those states come from `state` and `expired`.

The page does not call `useTrack`. `pagePath.ts` and `PagePath` both store `/charter-proposal/[token]`. `/charter-proposal/**` is `no-store, private`, the same as `/complete/**`. Copy does not call the acceptance a signature. There is no link into the booking flow.

### Checks
Engine lint, typecheck, test (80) and build passed. Fresh clone at `/tmp/anakata-fresh` typechecked and built the engine against sibling layer 0.13.0. `PagePathTest` passed (the charter path is stored as the placeholder). The browser was not opened on a Mailpit link, so accept, reopen, a superseded version and decline were not clicked through, and the network panel was not checked.

### Deviations
The view payload has no separate terms document. The tick sits next to the proposal HTML, and the version on the page is the proposal version. The accept response has the booking reference and `deposit_due_on`, and no deposit amount, so the amount shown after accept is `price.deposit` from the view.

Git (not run):

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add \
  app/pages/charter-proposal/\[token\].vue \
  app/utils/charterProposal.ts \
  app/utils/pagePath.ts \
  app/types/api.ts \
  i18n/locales/en.json \
  nuxt.config.ts \
  tests/unit/charterProposal.test.ts \
  tests/unit/pagePath.test.ts
git commit -m "$(cat <<'EOF'
Add the charter proposal page.

The document is the HTML the API rendered, and the token is redacted before it can be stored.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Engine/PagePath.php \
  tests/Unit/Engine/PagePathTest.php \
  docs/sprints/sprint-12/REPORT.md
git commit -m "$(cat <<'EOF'
Redact charter proposal paths and record the engine page.

EOF
)"
```
