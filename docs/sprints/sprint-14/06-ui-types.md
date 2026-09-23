# Task 06 · anakata-ui · Regenerate types, release `v0.15.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 14 · **Needs:** tasks 01–05 merged, the API on port 8000 with a fresh seed.

## Goal
The Journeys, Segments and Automations screens and the engine's two new surfaces type themselves from the API. Types only.

## Do
Same recipe as Sprint 13 task 05, including the enum rule (PHPDoc at the enum class; a hand-written union only where Scramble cannot emit one, with the reason).
1. **Inspect `/docs/api.json`** for: segments, the condition vocabulary and the contact list (01); the automation catalogue and its switch input (02); journeys with steps and enrolments (03); templates, versions, preview and test send (04); the engine's lead-capture and unsubscribe endpoints (05). Fix missing or empty shapes in one API prelude commit, asserted by the schema tests.
2. **Regenerate**; never edit `api.d.ts`; before and after line counts.
3. **Aliases** — `crm.ts` gains `Segment`, `SegmentCondition`, `SegmentVocabulary`, `AutomationRow`, `AutomationSwitchInput`, `Journey`, `JourneyStep`, `JourneyEnrolment`, `MessageTemplate`, `MessageTemplateVersion`, plus the inputs; `engine.ts` gains `MarketingLeadInput`, `UnsubscribeView` and `UnsubscribeInput`. Nothing new outside those two files.
4. **Release** `0.14.0 → 0.15.0`, CHANGELOG, README alias list; commit, tag after the commit, push HEAD then the tag. Pin `#v0.15.0` in the panel, engine and portal.
5. **Checks** in the layer; panel, engine and portal typecheck and build; fresh clone before the push (overlay) and after it (the real tag).

## Report
Append **Task 06**: the prelude, the schema → alias table, leftovers with reasons, line counts, the fresh-clone results, the pushed tag. Git commands listed, not run.
