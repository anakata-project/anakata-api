# Anakata Booking Engine — prototype handover

Everything needed to design and build the Anakata direct booking engine.

## What's in here

```
anakata-booking-engine/
├── README.md                              this file
├── SPEC.md                                full functional specification
├── prototype/
│   └── index.html                         the working prototype
└── reference/
    └── western-route-map.source.html      the route map as originally supplied
```

## Running it

Open `prototype/index.html` in any browser. No install, no build, no server. It needs an internet connection only for the fonts; it works offline with fallback fonts.

To see the whole flow quickly: set 2 adults, pick a month range from November 2027, open Western Realm's departures, choose one with a `−%` badge, open the Route map tab on step 3, continue, pick cabins on the deck plan, then on step 5 try the promo code `ANAKATA10` and compare the two booking options.

## What it is

A complete front end with real rules: live pricing with every discount and supplement, availability states, cabin validation, promo codes, departure offers, two booking paths, light and dark themes, phone layouts, and analytics events. It runs on seeded demo data — no backend, no payments, no real availability.

## What it is not

Production code. There is no framework, no build step and no tests. It exists to show exactly how the engine should behave and to let design and development work from something concrete rather than a written brief.

## Where to start

- **Designers** — §3 (screens), §9 (type, colour, motion, themes). Open the prototype in both themes and at phone width.
- **Developers** — §4 (pricing), §7 (what the backend must provide), §8 (analytics). `priceAll()` in the prototype is the single function that owns pricing.
- **Commercial** — §10, the open decisions. Nine items need an answer before launch.

## Demo data you'll see

- Departures run weekly from 7 November 2027, on two yachts, ANATIVA and ANAMARA.
- Rates: Suite USD 13,300 pp and Owner's Suite USD 25,000 pp in 2027, rising about 5% a year.
- Promo codes: `ANAKATA10`, `ADVISOR5`, `EARLY500`.
- Departure offers of −10%, −12% and −15% on four departures.

All of it is placeholder, defined in clearly marked tables near the top of the script.
