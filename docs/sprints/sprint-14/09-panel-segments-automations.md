# Task 09 · anakata-panel · Segments and Automations
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 14 · **Needs:** task 08.

## Goal
Audiences you can read and build from a fixed vocabulary, and one honest list of everything the system sends (Q1, Q5).

## Read first
- This sprint's REPORT tasks 01 and 02; the prototype `v-seg` / `renderSegments` (cards with the rule, dimension tags and what it feeds) and `v-auto` / `renderAutos` (sections a–g, trigger, timing, toggle)

## Do
1. **Segments** — `app/pages/crm/marketing/segments.vue` (replaces the placeholder; update the nav marker and test).
   - Cards from `GET /api/crm/segments`: name, live count, the rule sentence, the dimension tags in the prototype's five kinds, and what it feeds. Suppression is shown last and styled as the exception it is.
   - A card opens the contact list for that segment (paginated), each row opening the contact drawer.
   - New segment and Edit (non-system only, `contacts.manage`): a rule builder driven entirely by `GET /api/crm/segments/vocabulary` — field, operator, value, ANDed — with the live count refreshed as the rule changes. The panel knows no field names of its own.
2. **Automations** — `app/pages/crm/engine/automations.vue` (replaces the placeholder; update the nav marker and test).
   - Sections a–g from `GET /api/crm/automations`, each row: name, the subject line, trigger, timing, where it lives, audience (customer or staff) and kind. Rows marked "not built" are shown greyed with the API's note, so the list stays honest.
   - The toggle appears only on switchable rows and only with `rules.manage`, needs a reason, and shows who disabled it and when. A non-switchable row shows a short line saying the rule behind it must not depend on a switch.
3. **Cross-links:** an automation implemented by a journey step links to that journey; a staff row links to the alert kind on Alerts.

## Don't
- Don't hardcode a field, operator, section or kind list in the panel.
- Don't show a toggle where the API says the row is not switchable.
- Don't compute a segment count.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.15.0`.
- Browser, both themes, after `reset.sh`: the nine segment cards with counts matching their lists; build a new segment from the vocabulary and see its count; open a segment's contacts; the automations list with its sections, a disabled row with its reason, a non-switchable row with no toggle, and the two cross-links.

## Report
Append **Task 09**: both pages, the vocabulary-driven builder, the honest not-built rows, the cross-links, and the browser pass. Git commands listed, not run.
