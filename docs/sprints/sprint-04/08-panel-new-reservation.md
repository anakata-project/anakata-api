# Task 08 · anakata-panel · New Reservation
**Repo:** anakata-panel · **Sprint:** 4 (read `../anakata-api/docs/sprints/sprint-04/README.md` first)
**Needs:** task 07.

## Goal
The prototype's "New reservation — manual entry" modal creates one cabin, several cabins as a group (new or existing), or a whole-yacht charter, with a **live server quote**. It opens from the Bookings toolbar and from free Calendar cells (task 10).

## Read first
- `prototype/rms_index.html`:
  - `#newmodal` (every field, label and option, in order; the channel `<optgroup>`s; the group row; the back-to-back line; the price box `#nb-price`; internal notes; the buttons)
  - `openNew`, `nbType` (the charter alert text), `nbChan`, `paxCheck` (the warning texts), `nbAddCab` / `nbDrawX` (extra cabins), `nbGroups` (existing groups on the departure), `nbQuoteAll`, `saveNew` (the success messages)
- `../anakata-api/docs/sprints/sprint-04/REPORT.md`, task 03: the quote and create endpoints, warnings, errors, the 409 shape, contacts search

## Do
1. **`NewReservationModal.vue`** (square/hairline `UModal`, wide enough for the prototype's two-column layout), fields in the prototype's order:
   - **Booking type:** CABIN (FIT / Group) / CHARTER (full yacht). Choosing CHARTER hides the cabin rows and shows the prototype's charter notice as an inline `.notice`, not an alert. Its numbers come from the rates terms (`GET /api/rms/rates`), never hard-coded.
   - **Main channel** and **Channel of origin** from the API enums (labels and groups), not a panel copy.
   - **Agent / Agency and Commission %:** **not rendered** in Sprint 4 (agencies are Sprint 5). With a trade main channel, show a `.notice`: "Agency and commission are added in Sprint 5." Note it.
   - **Guest / client name** (required), plus **email**, **phone** and **preferred channel**. These three are new relative to the prototype, which has only a name; the API's contact needs an email to find repeat clients. Typing in the email field suggests existing contacts (`GET /api/rms/contacts?q=`); picking one fills the fields.
   - **Departure:** future departures in date order, labelled "7 Nov 2027 · ANAMARA · Western Realm" plus " · FESTIVE (+supplement, discounts blocked)" (prototype). Opening the modal from a calendar cell pre-selects the departure and cabin.
   - **Adults / Children (6–17):** the child age range from engine settings (`GET /api/rms/engine-settings`). Party warnings and errors come from the quote response and show in `#paxwarn` style.
   - **Cabin** (CABIN only): the departure's cabins with free ones enabled, "Suite 01 … Owner's Suite".
   - **Payment method for deposit:** **not rendered** (Sprint 5). Note it.
   - **＋ Add another cabin:** each extra cabin row has its own cabin and adults/children (prototype `nbDrawX`). Two or more cabins show the **group row**: Group name (placeholder "e.g. Alvear family & friends"), plus the OPS-008 notice.
   - **Existing group:** the `.tsel` select "— New reservation —" or the groups on this departure (`GET /api/rms/groups?departure_id=`). Choosing one adds the cabins to it.
   - **Back-to-back** checkbox (hidden for festive departures and charters, as the pricing rules ignore it there).
   - **Price box:** the per-cabin quote lines and totals from `POST /api/rms/bookings/quote`, re-requested 400 ms after any change (with stale-response protection, like the config editor). It shows "Deposit {pct}% · USD x · balance at T−{days}" from the quote. `NoRate` shows its reason in coral.
   - **Internal notes (never client-visible).**
   - **Buttons:** Cancel · **Create reservation**. Create is disabled while the quote has errors or is loading.
2. **Create:** `POST /api/rms/bookings`.
   - **201:** close; toast with the prototype's wording ("3 cabins created under GRP-008 (ANK-2026-0020, …). The coordinator receives all communications." or "Reservation ANK-2026-0020 created."); refresh the list; open the booking panel for the first booking.
   - **409 conflict:** the API sentence in the modal's `.warnbox`; the quote refreshes so the taken cabin shows as unavailable.
   - **422:** field errors.
3. **Unsaved changes:** closing the modal with input asks for confirmation (`confirmUnsaved`).
4. **Tests** (pure helpers):
   - the quote request builder (cabin rows → payload; charter → one party)
   - the group-row visibility rule (≥ 2 cabins, or an existing group chosen)
   - the departure option label
   - the success toast wording (1 vs n cabins)
   - the back-to-back visibility rule
5. **Browser check:**
   - As Carolina: one cabin (Suite, 2 adults, 2027) → the price box shows USD 26,600 / deposit USD 2,660 (`reference-values.md`) → create → the booking panel opens.
   - Three cabins → a new group with three bookings.
   - A festive charter for 19 Dec 2027 → the charter total with the festive supplement; nine cells `SOLD` in the Calendar (task 10).
   - Pick a taken cabin by racing two tabs → the conflict sentence.
   - 4 adults in one cabin → the capacity error; Create disabled.
   - As Lucía: she can create (Sales Exec has `bookings.create`), and she owns the result.

## Acceptance criteria
- [ ] The browser checks pass; the modal matches the prototype in both themes, apart from the documented omissions (agency, payment method) and additions (email, phone, preferred channel).
- [ ] No price is computed in the panel; everything comes from the quote endpoint.
- [ ] Lint, typecheck, test and build pass on a fresh clone.
- [ ] A "Task 08" section in the API's `sprint-04/REPORT.md` covering:
  - the omissions and additions
  - the toast wording
  - the quote debounce
