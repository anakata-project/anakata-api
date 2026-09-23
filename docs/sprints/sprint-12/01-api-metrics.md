# Task 01 · anakata-api · The metrics layer
**Repo:** anakata-api · **Sprint:** 12 (read `README.md` in this folder first)
**Needs:** the README's "Before task 01" done.

## Goal
One place that computes every commercial figure (O1), so the dashboard, the reports and any later screen read the same SQL and cannot disagree with Payments & Revenue or the calendar.

## Read first
- `docs/requirements/08-dev-decisions.md`: **O1, O8**, and H1–H5, I9, L2, N8
- doc 01 §9.1 (the metrics), §5 (the Payments & Revenue figures these must match)
- `PaymentsKpis`, the pipeline KPI SQL (Sprint 10 task 03), `ContactDerived`, `Availability`, `Booking::paidSql()` and the charges SQL, the NPS view (Sprint 11 task 05)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **One window, one scope.** `MetricWindow` (from, to, both inclusive Galápagos dates) and `MetricScope` (yacht, itinerary, channel group, agency — each optional). Every metric takes the same pair. State in the code which date each metric filters on: sale date (`bookings.created_at`), departure date, or payment date, because the same word means different things per metric.
2. **The metrics**, each a method on `App\Support\Metrics\CommercialMetrics`, each pure SQL, each tested against its source:
   - **occupancy by departure** — sold berths ÷ sellable berths from `Availability`, per departure and as an average over the window; a charter counts as the full yacht;
   - **RevPAB** — cruise revenue ÷ sellable berths;
   - **ADR** — cruise revenue ÷ berths sold;
   - **booking lead time** — average and median days between sale date and departure date, on sold bookings;
   - **channel mix** — sold bookings and revenue grouped by `channel_of_origin`, with the trade group named as the commission scan names it;
   - **nationality mix** — guest counts by nationality, aggregate only (O8);
   - **NPS** — average score and the promoter, passive and detractor counts from the thresholds in the business rules;
   - **commissions outstanding** — blocked, earned, payable and paid, from the Sprint 11 accrual SQL;
   - **cash** — collected, pending, overdue and the deposit share, reusing `PaymentsKpis` rather than a second query.
   Cruise revenue is the cabin charges of sold bookings, excluding extras and fees; say so in each PHPDoc.
3. **Equality tests.** For the seeded data and one random window: occupancy equals what the calendar shows; cash equals Payments & Revenue; commissions equal the agencies KPIs; NPS equals the guest-experience view. A difference is a failing test, not a rounding note.
4. **Endpoint.** `GET /api/rms/metrics?from=&to=&yacht=&itinerary=&channel=&agency=` (`panel.rms`): every metric in one payload, plus the window it used and the definition sentence for each figure (a PHP registry: what it counts, which date it filters on, what it excludes). The panel prints those sentences; it does not write its own.
5. **No personal data (O8).** No row of the payload names a guest or a contact. The sensitive-field walk covers the resource.
6. **Cost.** One query per metric, no N+1 over departures. Assert the query count is flat as bookings grow.

## Don't
- Don't store or cache a figure.
- Don't recompute anything that already has SQL; call it.
- Don't return a list of people.

## Checks
- `composer check`; the equality tests; the query-count test; the sensitive-field walk.

## Report
Create `docs/sprints/sprint-12/REPORT.md` with the heading `# Sprint 12 · Report`, then append **Task 01**: each metric with its formula, the date it filters on and what it excludes; the equality results; the endpoint. Git commands listed, not run.
