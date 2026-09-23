# Task 02 · anakata-api · The automation catalogue
**Repo:** anakata-api · **Sprint:** 14 · **Needs:** task 01.

## Goal
One list of every automatic message the system sends, what triggers it, where it is implemented, and — where it is safe — a switch (Q5).

## Read first
- `docs/requirements/08-dev-decisions.md`: **Q5**, Q4, and D5, J5, N1, N3, O3
- The prototype `AUTOS` (sections a–g) and `renderAutos` — the shape to match, not the source of truth
- Everything that already sends: `anakata:documents-due`, the balance reminders, the data chaser, the questionnaire and survey sends, the review request, the waitlist offer, the report schedules, the alert mailer, the portal invitation

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **The registry.** `App\Support\Automations\AutomationCatalogue`, one row per automatic message: key, section (the prototype's a–g), name, the subject line it sends, trigger in words, timing in words, where it lives (command, listener or journey step — the class or key), audience (customer or staff), kind (MARKETING or TRANSACTIONAL), and `switchable` (bool).
2. **Honest rows only.** Every row names something that exists. Anything in the prototype's list that this system does not send yet is listed with "not built" and the sprint or decision that would bring it, rather than being quietly dropped. The REPORT states the count of built versus not built.
3. **Switches.** `automation_settings`: `key`, `enabled`, `disabled_reason`, `disabled_by`, `disabled_at`, audit columns. Only `switchable` rows may be turned off; a rule-enforcing message (the overdue notice, the commission approval, the sync failure, the manifest data chase) may not, because the rule behind it must not depend on a toggle. Every sender asks the catalogue before sending: one helper, called in one place per sender, tested.
4. **What a switch does not do (Q5).** Disabling a message stops the message only. The alert still raises, the task is still created, the document is still issued, the flag is still set. A test asserts exactly that for the overdue case.
5. **Endpoints** (`panel.crm`; changing a switch needs `rules.manage`): `GET /api/crm/automations` — the registry with each row's current state, who disabled it and why; `PATCH /api/crm/automations/{key}` — enabled plus a reason, with history.
6. **Staff messages included.** The internal alerts (section g) appear too, marked as staff audience, pointing at the alert kinds (N1) rather than duplicating them.

## Don't
- Don't make a rule depend on a switch.
- Don't invent a row for a message that does not exist.
- Don't let a switch bypass consent — consent is checked separately and always.

## Checks
- `composer check`.
- Every sender consults the catalogue; a disabled switchable message is not sent and the reason is in history.
- The overdue case: message off, alert and task unchanged.
- A non-switchable key is refused with a sentence.
- The registry's built rows each resolve to a real class or journey key (a test walks them).

## Report
Append **Task 02**: the registry shape, the built and not-built counts, which rows are switchable and why the others are not, the endpoints, and the "a switch stops the message only" test. Git commands listed, not run.
