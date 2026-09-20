# INV-04 · Itinerary delete guard
- **Tags:** sprint-3, inventory
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
An itinerary that feeds departures must not delete. An unused draft can.

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/booking-engine/itineraries`. Open the **Western Realm** (WEST) card.
2. Read the Delete control. Close the drawer.
3. Click `＋ New itinerary`. Code `SOUTH`, name `Southern Isles`. `Save as draft`.
4. Click `Delete`. Confirm `Delete itinerary Southern Isles?`

## Expected
- [ ] E1 · WEST Delete is disabled and reads `Delete (has departures)`.
- [ ] E2 · SOUTH Delete is enabled (no departures). After confirm the SOUTH card is gone. Toolbar returns to `3 published · 3 total`.

## Notes
API 409 copy for a used itinerary is `Used by N departure(s)`. The panel disables Delete from `departures_count` instead of waiting for that 409.
