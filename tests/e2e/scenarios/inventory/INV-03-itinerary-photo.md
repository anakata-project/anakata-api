# INV-03 · Itinerary photo
- **Tags:** sprint-3, inventory
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
Hero upload is locked until the itinerary exists. After the first save, the card must show the file and pre-fill alt text.

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/booking-engine/itineraries`. Click `＋ New itinerary`.
2. Set **Code** `SOUTH`, **Name (public)** `Southern Isles`. Confirm the **Hero photo** file input is disabled and the hint `Save once to add a photo` is visible.
3. `Save as draft`. Upload `tests/e2e/fixtures/itinerary-hero.jpg` into **Hero photo**.

## Expected
- [ ] E1 · Before save, the file input is `disabled` and the hint is shown.
- [ ] E2 · After upload, **Photo alt text** is `Southern Isles — Galápagos` (pre-filled because it was empty).
- [ ] E3 · The SOUTH card hero is a `url(http://localhost:8000/storage/itineraries/…)` background (overlay `NO PHOTO — PLACEHOLDER GRADIENT` is gone).

## Notes
Alt prefill is `{name} — Galápagos` in `ItineraryEditor.vue`.
