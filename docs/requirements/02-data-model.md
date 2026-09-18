# 02 · Data model
Derived from the prototype. Field names are the prototype's; rename freely, but keep the semantics. Every entity needs `id`, `created_at`, `created_by`, `updated_at`, `updated_by`. Money is USD integers (no cents in the RMS; cents only on documents). Dates are ISO `YYYY-MM-DD`, UTC.

`examples/seed-data.json` is a live export of all of this with realistic values — use it as fixtures.

---

## Booking (§4.3)
| Field | Type | Notes |
|---|---|---|
| id | string | `ANK-YYYY-NNNN`; requests `ANK-R-YYYY-NNNN` |
| type | enum | CABIN · CHARTER |
| main_channel | enum | D2C · B2B · B2B – Travel Advisor · B2B – Tour Operator · B2B – Corporate · Wholesale / Distribution · Partners · Other |
| channel_of_origin | enum | 40 values in 4 groups (direct · marketing · trade & corporate · distribution/partners) |
| departure_id | FK | → Departure (yachts: ANAMARA · ANATIVA — identical twins) |
| cabin | enum | S1–S8 · OWNER · ALL (charter) |
| status | enum | see states below |
| adults / children | int | children = ages 6–17 on departure day |
| total / balance | int | cabin charges; extras and fees are separate |
| deposit_pct | int | frozen at creation; applies to cabin charges only (extras and fees are due 72 h before departure) |
| owner | FK user | own-records rule |
| group_id | FK | → Group (nullable) |
| commission | object | `{agency, rate, approved}` — B2B only |
| png_managed | bool | guest chose to pay the PNG park fee to Anakata → invoiced; otherwise information only |
| tct_managed | bool | Anakata manages the TCT card → invoiced |
| back_to_back | bool | −5% both weeks |
| price_lines | array | the quote at the time of sale (audit of how the price was built) |
| request | object | REQUESTED only: `{preferred_channel, party, expires, sla_left, advisor, notes}` |
| guests[] · extras[] · consents[] · payments[] · log[] · nps[] | | below |

### Booking states (§4.2)
`REQUESTED → PENDING_PAYMENT → CONFIRMED → FULLY_PAID → ON_BOARD → COMPLETED`, plus `OVERDUE` (flag over CONFIRMED), `ON_HOLD_AGENCY`, `WAITLISTED`, `RELEASED`, `CANCELLED`, `CANCELLED_POSTPAID`. Only legal transitions may be offered; the server must reject the rest. Every transition writes a history entry; reason mandatory for cancellations, manual FULLY_PAID and OPS-007 decisions.

## Guest / passenger (§4.3, §6.1, §6.4)
`first, last (as on passport), dob, nationality (ISO-2), ecuador_resident (bool), passport_no (encrypted), passport_expiry, email, insurance_declared (bool), medical_note (encrypted, ops only), lead (bool), guardian {name, relationship, consented_at}, preferences {…}`
Derived: `age_at_departure`, `is_minor_now`, `png_category + png_fee`, `complete` (name + DOB + nationality + passport + expiry + insurance).

## Consent record (§6.4)
`document (T&C · Cancellation policy · Privacy policy · Travel insurance declaration · Marketing), version, accepted_at, ip, source` — retain 7 years, never pre-checked, one row per document per booking.

## Extra service (§4.6 g)
Catalog: `code, name, unit, price|null (on request), triggers_transfer_voucher`.
On a booking: `code, qty, rate (frozen at sale), note`.

## Payment (§7)
`id, booking_id, date, kind (Deposit · Balance · Extras · Refund · Other), method (Card (Stripe) · Stripe payment link · Wire transfer), amount (negative for refunds), reference ANK-…-D01/B01/R01, gateway_id, status (Settled · Awaiting wire · Refunded), recorded_by`
Rules: deposit settles → CONFIRMED; balance reaches 0 → FULLY_PAID; wires are marked received manually (finance flag); every settled payment issues a payment confirmation.

## Group (OPS-008)
`id GRP-NNN, name, coordinator {name, email, phone, preferred_channel}, created_at` — bookings reference it. Same rates, conditions and process as FIT; the coordinator is the single point of contact.

## Agency (§5.5)
`id, name, contact, email, country, network, commission_pct, payment_terms, status (PENDING · APPROVED · REJECTED), requested_at, decided (who/when/why), users[] {name, email, status}, blocked_request {ref, value, departure}`
Approval SLA 2 business days. Commission > 12% is system-blocked (FIN-005).

## Itinerary (booking engine)
`code, name, status (PUBLISHED · DRAFT · HIDDEN), order, festive, days, nights, embark, disembark, tagline, hero_image, hero_alt, fallback_gradient, card_description, highlights[], chips[], overview, long_description, facts[6][2], day_plan[][2], included[], excluded[], faqs[][2], slug, meta_title, meta_description`

## Departure (booking engine + inventory)
`id DEP-NNN, date (Sunday), yacht, itinerary_code, status (ON_SALE · CLOSED · HIDDEN · CHARTER), urgency_threshold, waitlist (bool), public_note, festive (per date)`
Derived per departure: `sold · held · blocked · free` per cabin, `suites_free`, `owner_free`, engine label (AVAILABLE / ONLY N CABINS LEFT / FULL · WAITLIST / CLOSED / NOT SHOWN).

## Offer
`code, name, type (CREDIT · AMT · PCT · VALUE · COMM), value|value_text, channel (D2C · B2B · ALL), partner, cabin_types[], itineraries[], booking_window[from,to], travel_window[from,to], combinable, badge, price_line, show_on_card, show_on_departures, terms, status (DRAFT · PENDING · LIVE · PAUSED · EXPIRED)`
Never applies to festive departures; B2B offers never display publicly; price-affecting offers need Director approval.

## Rates (single source)
`years[], base {SUITE|OWNER|CHARTER: {year: price}}, terms {cabin_deposit_pct, cabin_balance_days, charter_deposit_pct, charter_deposit_days, charter_balance_days}, rules {single, triple, child, child_per_adult, child_per_cabin, back_to_back, festive_pax, festive_charter}`

## Engine settings
`guests {max_per_cabin, max_per_yacht, child_age[min,max], adult_required_with_children, under_age_message}, calendar {default_search[from,to], default_adults, horizon_months}, locale {default, live[], scaffolded[], currency}, copy {…6 blocks + 3 confirmation steps}, fees {tct, png_foreign_over_12, png_foreign_12_and_under, show_in_price_panel, footnote}, charter {headline, intro, itinerary_label, response_sla_hours, group_contexts[], thank_you}`

## Business rules (policies)
`commission_cap, default_commission, modification_fee, extras_due_hours, wire_window_hours, web_hold_min, web_hold_extension_min, hold_near_business_hours, hold_long_lead_business_days, response_sla_hours, refund_sla_business_days, reminder_days[2], manifest_fit_days, manifest_charter_days, cancellation_bands[{min_days, penalty_pct}]`

## Change history (all entities)
`at, actor, what, why` — append-only, never editable. Rates, engine settings and business rules each keep their own publish history with a mandatory approval reference.

---

## Pricing engine (§3.4.1 — apply in this order)
1. base rate × PAX (rate year = departure year)
2. child discount −15% ppdo, max 1 per adult and 2 per cabin, not festive
3. single supplement +75% ppdo when the cabin has one guest
4. triple discount −10% ppdo × 3 when three guests and no child rate, not festive
5. back-to-back −5%, not festive
6. festive supplement +USD 750 pp (FIT) / +USD 12,000 (charter) — blocks all discounts
7. deposit = deposit % of the total; balance due T−120

Verify against the prototype: Suite 2 adults 26,600 · single 23,275 · triple 35,910 · 2 adults + 1 child 37,905 · Owner's 2 adults 50,000 · festive 2 adults 28,100 · charter 199,500 · festive charter 211,500.

## PNG entry fee (FIN-004, per guest, age at departure)
under 2 exempt · foreign >12 USD 200 · foreign ≤12 USD 100 · CAN (CO/PE/BO) adult USD 100 · CAN minor USD 30 · Ecuadorian nationals and residents **USD 30 at any age** (Anakata 12 Sep 2026). TCT USD 20 per guest.
**Collection (decision 12 Sep 2026):** per booking the guest chooses whether Anakata collects the PNG fee; TCT likewise. Collected fees are invoiced and due with the balance; fees the guest pays directly appear on the invoice as information only and are excluded from the total.
**Deposit scope:** the deposit % applies to cruise (cabin) charges only. Extras and collected fees are due up to `extras_due_hours` (72 h) before departure; services added on board are settled during or after the cruise.
