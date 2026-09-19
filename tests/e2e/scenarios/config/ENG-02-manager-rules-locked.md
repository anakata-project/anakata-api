# ENG-02 · Manager can't touch rules
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Mateo
- **Start:** reset

## Why
E6: guests, calendar, fees and charter SLA need `engine_settings.manage`. Mateo has copy only.

## Steps
1. Sign in as Mateo. Open `/rms/booking-engine/settings`.
2. Inspect **Guests & capacity**, **Sales calendar & search**, **Galápagos fees shown in the price panel**, and **Private charter page** → `Response SLA (hours)`.

## Expected
- [ ] E1 · These inputs are disabled: `Max guests per cabin`, `Max guests per yacht`, child ages, default search months, horizon, all PNG/TCT fee amounts, `Response SLA (hours)`.
- [ ] E2 · Copy panels stay enabled: **Booking notes & messages**, **Confirmation page — what happens next**, charter `Headline` / `Intro` / `Group context options (one per line)` / thank-you.
- [ ] E3 · Rule panels show the `ADMIN` pill; copy panels show `ADMIN · MANAGER`.
