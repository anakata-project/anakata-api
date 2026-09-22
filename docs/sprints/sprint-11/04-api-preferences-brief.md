# Task 04 · anakata-api · Guest preferences and the hotel-manager brief
**Repo:** anakata-api · **Sprint:** 11 · **Needs:** task 03.

## Goal
Guests answer the pre-trip questionnaire from a link, staff can record answers on their behalf, and the hotel manager gets a printable brief per departure — with accessibility and emergency details encrypted and restricted (N6, N7).

## Read first
- `docs/requirements/08-dev-decisions.md`: **N6, N7**, B4, I1, I7, J5, J7, K9 (the complete page), OPS-006 in doc 03
- doc 01 §6.2 and §6.4
- `prototype/rms_index.html`: `PREF_Q` (questions, types, options, the two restricted ones), `renderGX` (the per-guest table and KPIs), `prefForm`, `savePref`, `hmBriefHtml`, and `pretripHtml` ("your preferences questionnaire arrives with this itinerary")
- `anakata:documents-due` (pre-trip at T−`documents.pretrip_days_before`), `DocumentPlanKind::Questionnaire`, `BookingAccessToken` and its purpose enum, the complete-page endpoints (the engine's `/api/engine/complete/*`), the sensitive-data cast

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Questions as code.** A PHP registry of the §6.2 questions in the prototype's order: key, label, type (text or one of fixed options), options, restricted (accessibility, emergency contact), required (none are). No Kosher option (OPS-006). Served at `GET /api/rms/guest-experience/questions` and to the engine's questionnaire endpoint. Wording is PENDING the guest-experience team; say so in a code comment and the report.
2. **Schema.** `guest_preferences`: `guest_id`, `version`, `answers` (JSON of the non-restricted keys), `accessibility` and `emergency_contact` (encrypted with the sensitive-data cast), `source` (GUEST_LINK, STAFF), `recorded_by` (nullable), `answered_at`, audit columns. The latest version per guest is current. Validation: option values from the registry, text max 500, unknown keys rejected.
3. **Sending.** In `anakata:documents-due`, when the pre-trip itinerary is sent to a booking, also send the questionnaire:
   - to every guest with an email, a link with a new token purpose QUESTIONNAIRE scoped to that guest;
   - for guests without an email, one link to the lead guest (or group coordinator) scoped to the booking, which lists those guests.
   Delivery kind `QUESTIONNAIRE`, J5 key `questionnaire:{booking}:{guest or lead}`; once each. Tokens expire on the return date.
4. **Engine endpoints** (token auth, like the complete page; CORS as today):
   - `GET /api/engine/questionnaire/{token}` — the booking's reference, departure and itinerary name, the guests the token covers (first name and cabin only) with their current answers (restricted answers shown back only as "provided" / empty — never the values), and the questions.
   - `PUT /api/engine/questionnaire/{token}/guests/{guest}` — the answers for one covered guest; a new version with source GUEST_LINK. 404 for a guest outside the token.
5. **Staff endpoints** (`panel.rms`; writing needs new permission `guest_experience.manage`, Admin and Manager by default; restricted values need `guests.view_sensitive` to read or write):
   - `GET /api/rms/departures/{departure}/guest-experience` — the prototype's view: KPIs (guests on board, bookings, questionnaires answered / total, sent or scheduled date, celebrations count, accessibility / medical count), and per guest: name, booking reference, email or "no email — sent to lead guest", cabin, status (ANSWERED with date and source, SENT — NO REPLY, SCHEDULED with date), dietary, celebration, activity. Restricted values only with `guests.view_sensitive`; otherwise their presence as a flag.
   - `GET /api/rms/guests/{guest}/preferences` and `PUT …` (source STAFF, `recorded_by`), with version history.
   - `GET /api/rms/departures/{departure}/hotel-manager-brief` — HTML, rendered on request, never stored, never emailed (N7): the prototype's sections (dietary, celebrations, accessibility only with `guests.view_sensitive`, special requests, room and rhythm counts, first time in Galápagos, how many have not answered). `?format=pdf` renders the same through the PDF renderer, streamed, not stored. History records who printed it.
6. **Captain's manifest.** Wire task 03's captain's manifest to the current preferences (dietary, emergency contact, accessibility) alongside the guest notes; a preference change counts as a passenger change for the next version.
7. **Retention.** `anakata:retention` purges `accessibility` and `emergency_contact` with the medical notes (`retention.medical_days_after_cruise`), and the rest of the answers with them (they are guest-experience data, not needed after the voyage); the rows stay with a `purged_at`.
8. **Separation.** No CRM route returns preferences; the arch test covers it; the sensitive-field walk adds `accessibility` and `emergency_contact`.

## Don't
- Don't store restricted answers unencrypted, or return them to the engine.
- Don't email or store the brief.
- Don't add questions beyond §6.2.

## Checks
- `composer check`.
- Sending at T−45: guests with email get their own link; the rest go to the lead; once each.
- Engine endpoints: token scope, validation, restricted values never echoed, expiry.
- Staff read and write with and without `guests.view_sensitive`; versions.
- The brief's sections with and without the permission; PDF renders.
- Retention purges on the date; the captain's manifest shows preferences.

## Report
Append **Task 04**: the question registry and its pending wording, the schema and encryption, sending and tokens, the engine and staff endpoints, the brief, retention. Git commands listed, not run.
