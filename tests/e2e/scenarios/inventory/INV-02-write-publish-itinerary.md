# INV-02 · Write and publish an itinerary
- **Tags:** sprint-3, inventory
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
A new itinerary must start as a draft with the documented defaults, refuse publish while blocking fields are empty, then publish once they are filled.

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/booking-engine/itineraries`. Toolbar is `3 published · 3 total`.
2. Click `＋ New itinerary`.
3. Confirm the defaults, then set **Code** to `SOUTH` and **Name (public)** to `Southern Isles`. Click `Save as draft`.
4. Click `Save & publish` without filling the card description or day plan.
5. Fill **Card description** with `Southern isles — a test itinerary.` In **Day by day**, click `＋ Add day` and set the body to `Day 1 at sea.` Click `Save & publish` again.

## Expected
- [ ] E1 · New drawer title `New itinerary`. Status DRAFT. **Days** `8`, **Nights** `7`, embark and disembark `San Cristóbal (SCY)`, **Card tagline** `8 days · 7 nights`. Card chips include `16 guests`. Day plan is empty. Photo file input is disabled with hint `Save once to add a photo`.
- [ ] E2 · After `Save as draft`, the drawer is an existing SOUTH itinerary (code locked). Toolbar `3 published · 4 total`. Card pill `DRAFT`.
- [ ] E3 · First `Save & publish` stays in the drawer and shows `Cannot publish — missing: card description, day-by-day plan.`
- [ ] E4 · Second publish succeeds. Card pill `PUBLISHED`. Completeness `54% COMPLETE · MISSING: HERO PHOTO, HIGHLIGHTS, LONG DESCRIPTION, URL SLUG, SEO TITLE, SEO DESCRIPTION`. Toolbar `4 published · 4 total`.

## Notes
Defaults: `App\Support\Itineraries\Defaults`. Completeness: 7 of 13 checks after name + card description + one day + default days/nights/includes/excludes/FAQs. Do not require 100%.
