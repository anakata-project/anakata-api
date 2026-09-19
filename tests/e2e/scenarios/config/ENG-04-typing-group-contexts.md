# ENG-04 · Typing group contexts
- **Tags:** sprint-2, config
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
The textarea must not rewrite itself from the parsed list while the user types (spaces and mid-line text would jump).

## Steps
1. As Carolina, open `/rms/booking-engine/settings`. Scroll to **Private charter page**.
2. In `Group context options (one per line)`, click at the end of the last line, press Enter, type `Board retreat` (do not blur yet). Watch the text while typing.
3. Fill approval `E2E-ENG-04`. `Save & publish`.

## Expected
- [ ] E1 · Seeded lines are `Family`, `Friends`, `Corporate / Incentive`, `Celebration`.
- [ ] E2 · While typing, `Board retreat` stays in the textarea (Enter adds a real newline; the box is not rewritten from `join`).
- [ ] E3 · After publish, history includes the new group context (`Board retreat`) in the change.

## Notes
Trailing spaces stay visible while typing and are trimmed on publish/reload.
