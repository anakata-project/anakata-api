# 01 · Functional specification — module by module
Anakata RMS v1.0 prototype · references are to ANK-COM-PRO-001-2026 v5.

Roles: **Admin** (Carolina — director + finance flags) · **Manager** (Mateo) · **Sales Exec** (Lucía).
Own-records rule: Manager and Sales Exec see every booking but can only modify their own. Every mutation must be enforced server-side, not only in the UI.

---

## RESERVATIONS

### 1. Booking Requests (§3.2, R-E7)
Queue of web "book now, pay later" requests (status REQUESTED) with cabins held.
- Columns: request id, contact + preferred channel, party, departure + cabin, estimated value, hold expiry, contact SLA countdown.
- SLA: contact within **24 h** via the guest's preferred channel (OPS-009). Breach is shown in red.
- Actions: **Confirm** → sends the deposit payment link, status → PENDING_PAYMENT · **Release** → reason required, hold returned to inventory, entry written to the audit list.
- Travel-advisor requests are flagged.

### 2. Calendar
Grid of cabins × departures for both yachts. Cell states: available · on hold · confirmed · fully paid · pending payment · requested · charter · internal block · "not yours" (own-records lock).
- Cells open the booking; empty cells offer to create one.
- Date-range filter (every list in the RMS has one).
- Availability must reach the public site in **< 30 s** (SLA, §10).

### 3. Yacht Layout
Deck plan per departure showing each cabin's state and party. Cabins are **Suite 01–08 + Owner's Suite** (confirmed 12 Sep 2026); the booking engine's 201–208 / 301 numbering must be changed to match. Yachts are **ANAMARA** and **ANATIVA** — identical twins: same hull, layout, cabin numbering and rates, so inventory logic is symmetric.

### 4. Bookings
All reservations, filterable by segment (D2C / B2B / Charter) and date, plus:
- **Groups panel** — multi-cabin reservations (OPS-008): group id, coordinator, cabins, guests, totals, statuses.
- **Deleted & released audit** — every deletion or released request with actor and reason.

#### Booking panel (six tabs)
**Overview** — type/channel, main channel, party, departure, itinerary, cabin, agency, group, totals, balance and due date; overdue resolution (OPS-007: alert, never auto-cancel — extension or cancellation, reason mandatory); request actions; legal status transitions only; free date change (FIN-006); post-trip survey entry for COMPLETED bookings.

**Guests** (§4.3, §6.1, §6.4) — one record per passenger:
- Name as on passport, DOB, nationality (+ "resident of Ecuador"), passport number and expiry, email, insurance declaration (OPS-005), medical/accessibility note.
- Passport numbers are masked for Sales Exec; medical notes are operations-only. Store encrypted (AES-256), restricted access, delete/anonymise 2 years after the cruise.
- Under-18 at time of booking → **guardian consent** (name, relationship, timestamp) is required.
- Validation: minimum age 6 on departure day (OPS-004) · passport expiring before the return date · children priced vs children present · missing consents.
- **PNG entry fee per guest** computed from age at departure + nationality (FIN-004): foreign >12 / foreign ≤12 / CAN adult / CAN minor / national / under-2 exempt.
- **Consent log**: 4 required documents (T&C, cancellation policy, privacy policy, insurance declaration) + optional marketing, each with version, timestamp, IP and source; kept 7 years; nothing pre-checked. Staff can record a consent obtained off-line (how it was obtained is logged).

**Extras** (§4.6 g) — contracted ancillary services (flights, pre/post hotel, spa, premium bar, boutique) with quantity, rate and note; subtotal flows to the invoice. Changing extras after the invoice re-issues it.
- **Galápagos fees, per booking (Anakata decision 12 Sep 2026):** the guest chooses whether the **PNG park entry fee** is paid to Anakata or directly at SCY airport on arrival, and whether Anakata manages the **TCT** card. Fees Anakata collects are invoiced and due with the balance; the rest print on the invoice as information only and are excluded from the total.
- **Deposit scope:** the deposit is calculated on cruise (cabin) charges only. Extras and collected fees are due **up to 72 h before departure** (editable in Business Rules); spa, premium bar and boutique can also be added on board and are settled during or after the cruise.

**Payments** (§7) — see Payments & Revenue below; per-booking ledger plus finance actions.

**Documents** (§4.5) — every document for this booking with trigger, date and status (sent / scheduled / waiting), each previewable and printable.

**History** — immutable log: who, when, what, why. Reason mandatory for cancellation, manual "fully paid", OPS-007 decisions, deletion and release.

---

## COMMERCIAL

### 5. Payments & Revenue (§7, §7.3)
- KPIs: collected, of which deposits, pending, overdue, commission accrued.
- **Pending payments** with due date (T−120) or the 72 h wire window.
- **Commissions** — earned / payable / blocked (>12% → Director approval, FIN-005).
- **Payment ledger** — every payment: date, booking, type (deposit / balance / extras / refund), method (Card (Stripe) · Stripe payment link · Wire transfer), reference `ANK-YYYY-NNNN-D01`, gateway id, status. Finance-only actions: record a payment, mark a wire received (auto status → CONFIRMED / FULLY_PAID, payment confirmation issued), execute refunds.
- **Payment platform reconciliation** — Stripe transactions vs RMS (TEC-001: Stripe confirmed 12 Sep 2026; transactional email via Microsoft Exchange): matched / in gateway but not in RMS (apply to booking) / to review. Wires reconcile against the OpCo bank statement.
- Required reports (not in the prototype): payments received daily, overdue daily, 30-day forecast weekly, monthly revenue, agent commissions payable, gateway reconciliation monthly.

### 6. Rates & Promotions (§4.1, §3.4)
Single price table, **Admin/Director only**, draft → publish with a mandatory approval reference and a full change history:
- Base rates per sailing year × Suite / Owner's Suite (per person, double) / Charter (per week); "+X% per year" helper with rounding; add or remove a year.
- Deposit % and balance days for cabins and charter (FIN-002/FIN-003).
- Discount and supplement rules: single +75%, triple −10%, child −15% (max per adult / per cabin), back-to-back −5%, festive +USD 750 pp / +USD 12,000 charter.
- **Extra services catalog** (flights, hotels, spa, bar, boutique).
- **Price check** — published vs draft across 8 scenarios before publishing.
- Every price in the RMS and on the engine reads this table. Confirmed bookings keep their contracted price and deposit %.

### 7. B2B & Agent Portal (§5.5)
- Registration requests with the **2 business-day** approval SLA (§10); approve / reject with reason; portal invite on approval.
- Agencies: network, commission (blocked above 12%), payment terms, bookings, revenue, commission accrued.
- Agency panel includes a **portal preview** — exactly what the agent sees: net rates only (public − commission), their bookings, commission history (payable 30 days post-cruise), sales materials. Public prices are never shown to agents; the client of record is always the end guest.

### 8. Contacts In (§5.1, §9.1)
Contacts created by bookings and requests, plus **guests by nationality (top 10)** — the KPI, and the driver of the PNG fee category.

---

## OPERATIONS

### 9. Holds & Waitlist (TEC-004, OPS-010, R-B2/R-B5)
Active holds (web 20 min + one silent 10 min extension; agency/charter 48 business hours near-term, 5 business days long-lead) and the waitlist (FIFO, notified when a cabin frees).

### 10. Refund Approvals (§4.1.5, LEG-001)
Cancellation penalty computed live from the bands (≥120 d 5% · 90–119 d 50% · 0–89 d 100%); refund due = paid − penalty. Director approval executes the refund and writes it to the payment ledger. Customer-facing legal text stays unpublished until PBP Law approves.

### 11. Internal Blocks (R-B6)
Fam trips, maintenance, negotiation holds, courtesies. Blocked inventory is unsellable and distinct in the calendar.

### 12. Documents & Manifests (§4.5–4.7)
- **Departure manifests** — passenger-data readiness per departure, DPNG list due (T−15 FIT / T−30 charter), captain's manifest (T−7); both printable. DPNG columns are provisional.
- **Client documents** — every document across all bookings with trigger and status: invoice & confirmation, booking summary, payment confirmations, balance reminders (due −21 / −7), pre-trip itinerary (T−45), preferences questionnaire (T−45), transfer voucher (T−7, if contracted), final invoice.
- Invoice structure follows mockup v6: vessel charges · Galápagos fees & TCT · ancillary services → three subtotals → invoice total; payment schedule; cancellation; insurance note; payment history; wire instructions (bank details pending LEG-004).

### 13. Guest Experience (§6.2–6.3)
- Pre-trip preferences per guest (13 fields), status by departure, and a printable **hotel manager brief** aggregating dietary needs, celebrations, accessibility, pillows, temperature, activity intensity.
- Post-trip survey: Captain/HM call first (MKT-006), then the 6-question survey; score < 7 → immediate alert to CEO + Operations (answer within 24 h); ≥ 8 → automatic public-review request.

---

## BOOKING ENGINE (what the public site reads)
### 14. Itineraries · 15. Departures · 16. Offers · 17. Engine Settings · 18. Engine Map
Content and configuration for anakata.co — see `04-booking-engine-contract.md`. Highlights:
- Itineraries: card + trip-details content, day-by-day, includes/excludes, FAQs, SEO; completeness score; cannot publish without name, description, day plan and duration.
- Departures: one row per yacht per Sunday; itinerary, on-sale status, urgency threshold ("only N cabins left"), waitlist, public note. **Availability is never typed** — it is computed from bookings, requests, holds and blocks. Sunday-only; date and yacht lock once cabins are sold or held; "generate season" creates weekly departures.
- Offers: badge and price-panel offers with channel, cabin types, itineraries, booking and travel windows; never on festive departures; price-affecting offers need Director approval; B2B offers never show publicly.
- Engine Settings: guest rules, sales calendar, locale (English only — Anakata decision 12 Sep 2026), the notes beside the price, Galápagos fee amounts, charter page copy.
- Engine Map: every element of the booking engine and which RMS field feeds it.

---

## ADMIN
### 19. Permissions
The matrix the server must enforce: view all · create · change status (own only for Manager/Agent) · move · delete (Admin) · manage users (Admin) · finance flag (mark wires, refunds) · director flag (commission > 12%, OPS-007 decisions, refunds, rates, business rules).

### 20. Business Rules (Admin only)
Registry of all 33 rules with source code, status (confirmed / pending legal / RMS spec / engine), current vs source value and a "differs" flag; 12 are editable here (commission cap, holds, SLAs, reminders, manifest deadlines, cancellation bands, wire window, modification fee), the rest link to where they are configured; 7 are structural and locked. Draft → publish with approval reference and history.
