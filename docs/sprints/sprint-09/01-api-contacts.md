# Task 01 · anakata-api · Contacts as CRM records; the derived fields
**Repo:** anakata-api · **Sprint:** 9 (read `README.md` in this folder first)
**Needs:** the README's "Before task 01" done.

## Goal
The `contacts` table the RMS has used since Sprint 4 becomes the CRM's people record: a few owned fields staff can edit, and derived fields — lifecycle, lifetime value, segment, consent summary — computed from the bookings and the consent log, never typed.

## Read first
- `docs/requirements/08-dev-decisions.md`: **L1, L2, L9, L10**, and A2, B9, D4, D5, I6
- `07-three-system-integration-contract.md` §2, §3 (the Contact, Attribution, Consent and Lifecycle rows), §8
- `prototype/crm_index.html`: `v-contacts` (notice, filters, columns, the hint about derived lifecycle), `CONTACTS`, `renderContacts`, `ltvOf`, `segOf`, `openContact`
- `Contact`, `ResolveContact`, `GuardCrmSensitiveData`, the CRM arch tests, `routes/api/crm.php`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Owned fields** (new migration on `contacts`): `type` (`DIRECT_PASSENGER · TRAVEL_AGENT · CORPORATE_CHARTER`, default DIRECT_PASSENGER), `language` (ISO 639-1, default `en`), `phone_e164` (nullable, task 02 fills it), `first_touch` and `last_touch` (JSON, nullable; task 03 fills them). Existing name, email, phone, country and preferred channel stay.
   - Defaults at creation: the agency contact of an agency → TRAVEL_AGENT; a contact created from a charter enquiry → CORPORATE_CHARTER; otherwise DIRECT_PASSENGER. `ResolveContact` never downgrades an existing contact's type.
   - `language` is stored for information; every customer message stays in English (L10).
2. **Derived fields (L2)** — one query scope, `Contact::scopeWithDerived()`, adding them as SQL aggregates so a list never loads bookings row by row:
   - **Lifetime value:** the sum of `charges_total` of the contact's bookings in a sold status (CONFIRMED, FULLY_PAID, ON_BOARD, COMPLETED — and OVERDUE if it is ever used), through the Sprint 6 charges SQL so it never disagrees with the booking. A cancelled booking drops out by itself (prototype `ltvOf`).
   - **Segment:** HIGH above `crm.segment_high_ltv`, MID from `crm.segment_mid_ltv`, else NEW (prototype `segOf`). Both thresholds are new business rules (below).
   - **Lifecycle**, by precedence: **GUEST** (a booking ON_BOARD) → **BOOKED** (a sold booking whose departure is still ahead) → **SQL** (a REQUESTED, PENDING_PAYMENT or ON_HOLD_AGENCY booking) → **PAST GUEST** (a COMPLETED booking and nothing ahead) → **MQL** (identified engine behaviour — task 03 — or a marketing consent, with no booking) → **PROSPECT**. **AGENT** replaces all of these for a TRAVEL_AGENT contact whose agency is ACTIVE in the RMS (the prototype's "AGENT from the RMS partner approval"). Write the rule as a SQL `CASE` in one place and test each branch; the MQL branch reads a column task 03 adds, so leave it returning false with a `TODO(task 03)`.
   - **Consent summary:** `marketing` = the contact's latest MARKETING consent across their bookings (Sprint 6's log) is accepted and not withdrawn; `transactional` is always true. Sprint 10 replaces this with the consent register; keep it behind one method so that is a one-place change.
   - **NPS:** not built until Sprint 11; the resource says `null`, and the panel shows "—".
3. **Business rules shape change:** `crm.segment_high_ltv` (20000) and `crm.segment_mid_ltv` (8000), PENDING CLIENT, the same procedure as previous shape changes (DML migration, `anakata:config-verify` before and after, registry rows and counts, BR fixtures).
4. **Endpoints** in `routes/api/crm.php` (Sanctum, `panel.crm`, the CRM sensitive-data guard on every route):
   - `GET /api/crm/contacts` — filters `type`, `lifecycle`, `main_channel` and `channel_of_origin` (of the contact's first booking — the attribution the RMS already stores), `consent` (`marketing` / `transactional_only`), `q` (name, email, phone); paginated; the derived fields on every row. The filter options for type and lifecycle come back in `meta.filters` with labels (the Sprint 7 pattern), so the panel holds no lists.
   - `GET /api/crm/contacts/{contact}` — the profile: owned and derived fields, the first-booking attribution, and the contact's bookings read directly from the RMS tables (reference, departure, status, `charges_total`, balance) through a CRM resource — no guest rows, no payments detail, no notes.
   - `PATCH /api/crm/contacts/{contact}` — owned fields only: name, email, phone, country, language, preferred channel, type. A new email that belongs to another contact → 409 naming it and suggesting a merge (task 02); history `contact.updated` with field names; permission `contacts.manage` (new, Admin and Manager and Sales Exec by default — the CRM is the sales team's tool).
5. **Visibility (L1):** every user with `panel.crm` sees every contact. Bookings shown on a contact keep their own-records rule for any action, but reading them here is allowed — the CRM reads, never writes. This also answers the Sprint 4 question about `GET /api/rms/contacts?q=`: leave that endpoint as it is (it already shows every contact), and record the decision and the README client question.
6. **Separation (L9):** the resources live in `App\Http\Resources\Crm`; extend the arch test so CRM controllers cannot use any action under `App\Actions\Payments`, `App\Actions\Bookings`, `App\Actions\Guests`, `App\Actions\Extras` or `App\Actions\Documents`; and a feature test walks every CRM response for the `SensitiveFields` keys.

## Don't
- Don't store lifecycle, lifetime value or segment.
- Don't let the CRM change a booking, a payment or a guest.
- Don't hide contacts by owner.

## Checks
- `composer check`; `anakata:config-verify` before and after the shape change.
- Lifetime value and segment for a contact with confirmed, fully paid, cancelled and requested bookings; cancelling one moves the numbers.
- Every lifecycle branch, including AGENT and precedence (a past guest with a new request is SQL).
- The list's query count stays flat as contacts and bookings are added.
- PATCH: owned fields only; the email conflict 409; history names fields, not values.
- The arch test and the sensitive-field walk.

## Report
Append **Task 01** to `docs/sprints/sprint-09/REPORT.md`: the owned and derived fields and their rules, the shape change and counts, the endpoints and filters, the visibility decision and the Sprint 4 question, and the separation tests. List the git commands; do not run them.
