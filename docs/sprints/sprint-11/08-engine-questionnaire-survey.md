# Task 08 · anakata-engine · Questionnaire and survey pages
**Repo:** anakata-engine (plus the sprint REPORT) · **Sprint:** 11 · **Needs:** task 07 (`v0.12.0`).

## Goal
Guests answer the pre-trip questionnaire and the post-trip survey from the links in their emails, on pages that look like the rest of the engine and never show anything the token does not cover.

## Read first
- This sprint's REPORT tasks 04 and 05
- The engine's `complete/[token].vue` (token handling, layout, error states, the consent banner rules), `useTrack`, the i18n file
- `prototype/rms_index.html` `PREF_Q` and `NPS_Q` (the questions and their order; the API serves them — do not copy them)

## Do
1. **`/questionnaire/[token]`**
   - Load `GET /api/engine/questionnaire/{token}`.
   - Header: booking reference, itinerary, departure date. Then one section per covered guest (first name and cabin).
   - Each guest section has the API's questions in order, rendered by type (text or select). Restricted questions (accessibility, emergency contact) carry a line saying they are seen only by the operations team; when the API says an answer was provided, the field shows "Provided — enter again to replace" and is empty.
   - Save per guest with `PUT`. Show the saved state and the API's validation messages. An expired or unknown token shows the same message the complete page uses.
2. **`/survey/[token]`**
   - Load `GET /api/engine/survey/{token}`.
   - One form per covered guest who has not responded: score 1–10 and recommend 0–10 as button rows, plus the four text questions from the API.
   - Submit with `POST`. A guest who already responded shows "Thank you — received". A 409 shows the same.
3. **Privacy and tracking.**
   - Both pages send no behavioural events, and `useTrack` is not called on them.
   - Their `page_view` path redaction joins `/complete/[token]`: `/questionnaire/[token]` and `/survey/[token]`, so the token never leaves the browser.
   - The consent banner rules are unchanged.
4. **Tests.** Path redaction for both routes. The restricted-field behaviour (never pre-filled). The score inputs' bounds.

## Don't
- Don't hard-code questions, options or scores' labels.
- Don't show another guest's answers, or anything outside the token's scope.
- Don't add links from these pages into the booking flow.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.12.0`.
- Browser, both themes, against the running API:
  - open a questionnaire link from Mailpit and answer for two guests (one via the lead's link);
  - confirm a restricted answer is not shown back;
  - open a survey link, submit a 6 and a 9, and reopen it to see "received";
  - use an expired token;
  - check in the network panel that no `/api/engine/events` request carries these paths unredacted.

## Report
Append **Task 08**: both pages, the redaction change, tests and the browser pass. Git commands listed, not run.
