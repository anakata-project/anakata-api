# Task 04 · anakata-api · Templates, preview and test sends
**Repo:** anakata-api · **Sprint:** 14 · **Needs:** task 03.

## Goal
Every journey step's words are a versioned record that can be read back years later, previewed before it goes out, and tested without touching a real contact (Q8).

## Read first
- `docs/requirements/08-dev-decisions.md`: **Q8**, Q4, Q7, and D5, I6, J2 (immutable versions), O9
- The existing mail layouts and the document blade layouts; the prototype's step names and subject lines (`JOURNEYS`, `AUTOS`)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.** `message_templates`: `key`, `name`, `kind` (MARKETING or TRANSACTIONAL, matching its journey), audit columns. `message_template_versions`: `template_id`, `version`, `subject`, `body` (structured text: paragraphs, a list, one call-to-action with its link key — not free HTML), `variables` (the ones it uses), `published`, `published_by`, `published_at`, `approval_reference` (required, as rate and rule publishing already requires), audit columns. Versions are immutable; publishing a new one leaves the old.
2. **Variables, from a fixed list.** `{{first_name}}`, `{{booking_reference}}`, `{{departure_date}}`, `{{itinerary_name}}`, `{{balance_due_date}}`, `{{deposit_link}}`, `{{complete_link}}`, `{{unsubscribe_link}}` and the handful the eight journeys need. The resolver fills them from the enrolment's contact and booking; an unresolved variable is a publish-time error, not a blank in a sent message.
3. **Marketing templates must carry the unsubscribe (Q7).** Publishing a MARKETING version without `{{unsubscribe_link}}` is refused with a sentence. Transactional templates must not carry it.
4. **Rendering.** One layout for both kinds, English, the same shell as the existing transactional mail. A test asserts a rendered message contains no personal data beyond the variables it declared (O9's walk, applied to mail).
5. **Preview and test send.** `POST /api/crm/templates/{key}/preview` with a contact or booking id returns the rendered subject and body without sending. `POST …/test-send` sends one copy to the requesting staff user's own address only — never to a contact — recorded as a staff send (O3's pattern), with history.
6. **Endpoints** (`panel.crm`; publishing needs `rules.manage`): list templates with their published version and drafts, read a version, create a draft, publish with an approval reference.
7. **Seed** the templates the eight journeys reference, with the prototype's step names as their names and its subject lines where it gives them; where it does not, a plain English subject and a note in the REPORT that the copy is pending the client (README question).

## Don't
- Don't allow raw HTML or a script in a body.
- Don't send a test to anyone but the requester.
- Don't let a journey send an unpublished version.

## Checks
- `composer check`.
- Version immutability; publish with and without an approval reference; a marketing template without the unsubscribe variable is refused.
- Preview resolves every variable for a real contact and refuses an unknown one.
- Test send goes only to the requester and is recorded.
- A journey step refuses to send when its template has no published version.

## Report
Append **Task 04**: the schema and immutability, the variable list, the unsubscribe rule, preview and test send, the seeded templates and which copy is pending. Git commands listed, not run.
