# Task 07 · anakata-engine · The checkout marketing tick and the unsubscribe page
**Repo:** anakata-engine (plus the sprint REPORT) · **Sprint:** 14 · **Needs:** task 06 (`v0.15.0`).

## Goal
Ask for the address honestly, and let people leave in one click (Q6, Q7).

## Read first
- This sprint's REPORT task 05; the checkout details step and its existing marketing opt-in at submit; the complete, questionnaire and survey token pages as the pattern for a public page

## Do
1. **The details step.** Beside the email field, an unticked box with the API's consent text for that version: what Anakata will send and that it can be stopped at any time. Ticking and moving on (or leaving the step) posts `marketing_lead` once per session; unticking before that posts nothing. Nothing is posted while the box is unticked, and the UI never implies the address was saved when it was not.
   - The submit-time marketing opt-in stays exactly as it is; a person who ticked at details and again at submit produces two register rows, which is correct, and the REPORT says so.
2. **`/unsubscribe/[token]`.** Loads the API's view, shows one sentence and one button, posts, then confirms in plain English with a line saying transactional messages about an existing booking continue. A second visit shows the same confirmation. An unknown token shows a neutral message with no detail.
3. **Privacy.** The page sends no behavioural events and calls no tracker. `pagePath.ts` and the API's `PagePath` both collapse `/unsubscribe/[token]`. `routeRules` for `/unsubscribe/**` match the other token pages, including `noindex`.
4. **Tests.** The tick posts once and only when ticked; unticking after a post does nothing (withdrawal is the unsubscribe path, and the copy says so); path redaction for the new route; the confirmed and unknown-token states.

## Don't
- Don't pre-tick the box, or hide it behind a link.
- Don't post an address on keystroke; post once, on the deliberate action.
- Don't put anything but the token in the unsubscribe URL.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.15.0`.
- Browser, both themes: tick and abandon the checkout → the contact and its register row exist, and the nurture journey has an enrolment; abandon without ticking → nothing exists; open an unsubscribe link from a journey email → suppressed, enrolments exited, second click idempotent; no unredacted token in any events request.

## Report
Append **Task 07**: the details-step box and its posting rule, the unsubscribe page, redaction, tests, and the browser pass. Git commands listed, not run.
