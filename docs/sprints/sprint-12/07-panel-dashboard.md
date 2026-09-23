# Task 07 · anakata-panel · Commercial Dashboard
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 12 · **Needs:** task 06 (`v0.13.0`).

## Goal
One screen that answers "how are we doing", with every number owned by the API (O1, O8).

## Read first
- This sprint's REPORT task 01; doc 01 §9.1
- The existing KPI and chart patterns on Payments & Revenue and Pipeline; `useMoney`, `useDates`

## Do
1. **Page** `app/pages/rms/commercial/dashboard.vue`, a new first item in the Commercial nav group (`panel.rms`, sprint 12). Extend the navigation test.
2. **Filters:** the shared window (the existing DateRangeFilter, defaulting to the current year) plus yacht, itinerary, channel and agency. One request to `GET /api/rms/metrics`; every panel on the page reads that payload. Changing a filter refetches once, debounced like the other lists.
3. **Layout:**
   - a KPI row: occupancy, RevPAB, ADR, average lead time, NPS, commissions outstanding;
   - cash from the same payload, matching Payments & Revenue (collected, pending, overdue, deposit share);
   - occupancy by departure as a table with the completeness-style bar already in the panel, coral below the low-occupancy rule;
   - channel mix and nationality mix as simple ranked tables with counts and share;
   - NPS as the three counts plus the average.
   No new chart library. If a visual needs more than the existing bar, use a table.
4. **Definitions.** Each figure has an info line from the API's definition sentence (what it counts, which date it filters on, what it excludes). The panel writes none of those sentences itself.
5. **No drill-through to people (O8).** A departure row links to the calendar; nothing links to a guest or a contact.
6. **Empty and partial windows:** the API's zero or null renders as an em dash, never as 0%.

## Don't
- Don't compute, sum or average anything in the page.
- Don't cache the payload across filter changes.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.13.0`.
- Browser, both themes, after `reset.sh`: the KPI row against Payments & Revenue for the same window (equal), occupancy against the calendar for one departure, filters, an empty window, and that no cell reaches a person.

## Report
Append **Task 07**: the page, the single request, the definition lines, and the browser comparison. Git commands listed, not run.
