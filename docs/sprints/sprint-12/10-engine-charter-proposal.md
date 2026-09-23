# Task 10 · anakata-engine · The charter proposal and acceptance page
**Repo:** anakata-engine (plus the sprint REPORT) · **Sprint:** 12 · **Needs:** task 06 (`v0.13.0`).

## Goal
The client opens their charter proposal from the link in the email, reads it, and accepts or declines — with the acceptance recorded properly (O5).

## Read first
- This sprint's REPORT task 05
- `complete/[token].vue` and the questionnaire and survey pages (token handling, layout, error states), `pagePath.ts` (redaction)

## Do
1. **`/charter-proposal/[token]`** from `GET /api/engine/charter-proposal/{token}`:
   - the proposal's own HTML as the API renders it (the panel and the engine show the same document), with the version, the price summary and the validity date beside it;
   - **Accept**: full name typed, and a tick for the terms and the cancellation policy, whose text and version come from the API. Posting shows what happens next: the booking reference, the deposit amount and its due date, all from the response. A second accept, or an expired or superseded version, shows the API's sentence.
   - **Decline**: an optional reason, then a short acknowledgement.
2. **Wording.** Nothing on the page calls this a signature; the copy is the API's. The i18n file holds only chrome (titles, buttons, the accepted and declined states).
3. **Privacy.** No `useTrack`, no behavioural events. `pagePath.ts` and the API's `PagePath` both collapse `/charter-proposal/[token]`, so the token never leaves the browser. Add `routeRules` for `/charter-proposal/**` matching `/complete/**`.
4. **Tests.** Path redaction for the new route; the accept form refuses an empty name or an unticked box before posting; the expired and already-accepted states render from the API's status.

## Don't
- Don't render the proposal from the JSON; show the document the API renders.
- Don't call the acceptance a signature.
- Don't link into the booking flow from this page.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.13.0`.
- Browser, both themes, against the running API: open a proposal from Mailpit, accept it and see the booking and deposit; reopen and see the accepted state; open a superseded version; decline another proposal; confirm the network panel shows no unredacted token in an events request.

## Report
Append **Task 10**: the page, the accept and decline flows, redaction, tests, and the browser pass. Git commands listed, not run.
