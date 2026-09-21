# GST-05 · Passport expiring before the return date
- **Tags:** sprint-6, guests
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
OPS / DPNG need a passport valid through the Sunday return. Saving an expiry before `Departure::returnDate()` must flag the guest without blocking the save.

## Steps
1. Sign in as Carolina. Open ANK-2026-0005. **Guests** tab.
2. **Edit** Julia Brandt. Set Passport expiry to `2027-11-01` (return is 14 Nov 2027). Do not change or copy the passport number. `Save guest`.
3. Read the issues warnbox.

## Expected
- [ ] E1 · Toast `Guest saved` — the save is not blocked.
- [ ] E2 · Warnbox includes `✕ Julia Brandt's passport expires before the return date (14 Nov 2027).`

## Notes
Cruise is 7 Nov → 14 Nov 2027 (DEP-001). Never write Julia’s passport number into the report. Reference her by name.
