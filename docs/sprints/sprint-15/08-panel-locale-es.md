# Task 08 · anakata-panel · Spanish locale

**Repo:** anakata-panel · **Sprint:** 15 · **Needs:** nothing from this sprint's other tasks — can run in parallel with Tasks 01–07, though new strings those tasks add (inbox, B2B Partners) should land in `en.json` before this task's translation pass, or this task's Spanish file will miss them.

## Goal

Internal panel screens can be read in Spanish. This does **not** touch the booking engine or the portal's guest/agent-facing copy — English-only for guests and agents is a confirmed 12 Sep 2026 decision and stays. This is staff tooling only.

## Repo check to do first

- Confirm `panel/i18n/locales/en.json` is genuinely the only locale file today (the Sprint 15 README's repo-check found this; re-confirm at the point this task actually runs, since Tasks 05/06 will have added new keys to it in the meantime).
- Confirm the panel's `nuxt.config.ts` `i18n` block (Sprint 0's REPORT records the initial setup: `@nuxtjs/i18n`, `restructureDir: i18n`, `langDir: locales`, `en` declared) to know exactly how to register a second locale without breaking the existing merge with the `anakata-ui` layer's own `theme.*` keys, which Sprint 0 was careful about.
- Confirm whether any string in `en.json` is already business-sensitive text that shouldn't be casually translated (e.g. exact legal/compliance wording, the LEG-002-pending consent labels Sprint 14 added) — those need the same "pending" treatment in Spanish, not a translated-but-still-placeholder version that reads as more final than it is.

## Do

1. **Add the locale.** Register `es` alongside `en` in the i18n config, without disturbing the existing layer-merge behavior Sprint 0 set up.

2. **Translate `es.json`.** A full Spanish translation of every key in `en.json` as it stands after Tasks 01/05/06/09 land (translate last, once the string set is final for this sprint). Keep interpolation tokens (`{name}`, `{count}`, etc.) and any embedded HTML/markup exactly as in the English source — don't restructure sentences in a way that breaks a token's position if the component assumes a fixed structure.

3. **Locale switcher.** A control in the topbar or user menu (wherever the existing theme toggle lives — reuse that location's pattern rather than adding a new settings surface) to switch between English and Spanish. Persist the choice client-side (a simple stored preference, not a new API field — there's no product reason this needs to sync across devices or be visible to other staff).

4. **Date/number formatting.** Confirm `useDates()` (the composable Sprint 14 required for all due-date rendering) and any currency formatting already used across the panel react to the active locale correctly, or explicitly still format en-US style regardless of UI language (state which, deliberately, in the REPORT — don't let this be an accidental inconsistency).

## Don't

- Don't translate the engine or the portal. If either later gets its own Spanish requirement, that's a separate, explicitly-scoped task — this one is panel-only per the goal above.
- Don't translate data the API returns as free text (a template's subject/body content from Sprint 14, an inbound email's body from Task 01, a contact's notes field). Only the panel's own UI chrome — labels, buttons, headings, validation messages — is in scope. User-entered or business content stays exactly as entered.
- Don't ship a partial translation silently. If some keys can't be reasonably translated without client input (a legal disclaimer, a LEG-002-pending string), leave them in English with a note in the REPORT rather than guessing at legal Spanish.

## Tests

- Every key present in `en.json` has a corresponding key in `es.json` (a test that diffs the two key sets, failing on any mismatch in either direction) — this catches both missing translations and orphaned Spanish keys after a future English string changes.
- Switching locale updates rendered UI text without a full page reload.
- The locale preference persists across a session reload.

## Checks

`pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`. Fresh-clone typecheck/build against the released `anakata-ui` tag.

Browser, both themes, both locales: spot-check the CRM, RMS, and the new Task 05/06 pages render fully in Spanish with no leftover English strings and no broken interpolation.

## Report

Append **Task 08**: the key-parity test, the date/number-formatting decision and why, which strings were deliberately left in English and why (legal-pending content), and confirmation the engine and portal were not touched. Git commands listed, not run.
