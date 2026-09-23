# Task 01 · anakata-api · Segments and suppression
**Repo:** anakata-api · **Sprint:** 14 (read `README.md` in this folder first)
**Needs:** the README's "Before task 01" done.

## Goal
Audiences that are rules over what the system already knows, with one suppression rule that overrides them all (Q1, Q2).

## Read first
- `docs/requirements/08-dev-decisions.md`: **Q1, Q2**, and L2, L6, M1, M2, M8
- The prototype `SEGMENTS` and `renderSegments` (nine definitions, their rules, their dimension tags and what each feeds)
- `ContactDerived` (lifecycle, segment, LTV in SQL), the behavioural events table, the consent register, `ConsentGate`, the campaign measurement SQL

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.** `segments`: `key`, `name`, `sentence` (the rule in words, shown on screen), `conditions` (JSON), `dimensions` (JSON, the prototype's BEHAVIOUR / INTEREST / LOCATION / PROFILE / PROMOTION tags), `kind` (MARKETING or OPERATIONAL), `system` (bool — the nine seeded ones cannot be deleted), `active`, audit columns. No membership table (Q1).
2. **Conditions, as a small vocabulary** rather than free SQL: each condition is a field, an operator and a value, over a fixed list of facts — behavioural event counts in a window, booking status and history, lifecycle, LTV band, NPS, consent state, country, party shape, agency, campaign exposure, last activity. `SegmentQuery` turns a definition into one SQL query. Anything the vocabulary cannot express is not a segment; say so rather than adding an escape hatch.
3. **The nine seeded definitions** from the prototype: warm dreamers, abandoned checkout, holding — not paid, festive prospects, families 6–17, past guests HIGH LTV, advisors — non-producing, DACH luxury, and suppressed. Each with the prototype's rule sentence, rewritten to match what this system can actually check, and the sentence in the REPORT where the two differ.
4. **Suppression (Q2).** The `suppressed` definition is special: marketing consent withdrawn or never given, an erasure, a hard bounce (a delivery outcome the mailer records), or an unsubscribe. `Suppression::applies(contact)` and a SQL form of the same rule. Every marketing audience and every journey step subtracts it, on top of `ConsentGate`. One test proves a suppressed contact appears in no marketing segment.
5. **Endpoints** (`panel.crm`): `GET /api/crm/segments` with each definition, its dimensions, its live count and what it feeds; `GET /api/crm/segments/{key}/contacts` (paginated, the contact rows the CRM already returns); `POST` and `PATCH` for non-system definitions (`contacts.manage`); `GET /api/crm/segments/vocabulary` — the fields, operators and values the panel builds a rule from, so nothing is hardcoded there.
6. **Cost.** Counts for the list come from one query per definition, not one per contact; the list caps at a sensible number of definitions and says so. Assert the query count is flat as contacts grow.

## Don't
- Don't store membership, or a count, anywhere.
- Don't allow raw SQL in a definition.
- Don't let a marketing segment include a suppressed contact under any circumstances.

## Checks
- `composer check`.
- Each seeded definition's count equals the rows it lists; a contact who matches two appears in both.
- A withdrawal moves a contact out of every marketing segment and into suppression immediately.
- The vocabulary endpoint covers every operator the seeded definitions use.
- Query-count test.

## Report
Create `docs/sprints/sprint-14/REPORT.md` with the heading `# Sprint 14 · Report`, then append **Task 01**: the schema, the condition vocabulary, the nine definitions and where they differ from the prototype, suppression, the endpoints. Git commands listed, not run.
