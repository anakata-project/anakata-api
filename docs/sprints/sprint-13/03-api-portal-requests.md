# Task 03 · anakata-api · Booking requests from the portal
**Repo:** anakata-api · **Sprint:** 13 · **Needs:** task 02.

## Goal
An agent can ask for a booking, and what arrives in the RMS is an ordinary REQUESTED booking with the agency attached — no special path, no special rules (P4).

## Read first
- `docs/requirements/08-dev-decisions.md`: **P4**, P3, P5, and G2, H8, K10, M6, N1
- doc 03 FIN-005 (the 12% cap and the Director decision), OPS-009 (the 24-hour response SLA)
- The engine's request path (`SubmitEngineCheckout` and the booking it creates), `ResolveContact`, the agency commission freeze, the ON_HOLD_AGENCY rule, the REQUEST_RESPONSE task (Sprint 11 task 04)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`POST /api/portal/requests`** (`portal.auth`): departure, cabin category, cabins, guests per cabin, the client's name and email, the agent's notes, and an acknowledgement that the client of record is the end guest. It creates the booking through the same action the engine uses, with:
   - the agency and its current commission frozen on the booking (H8);
   - the status REQUESTED, or ON_HOLD_AGENCY when the agency's rate is above `commission.cap_pct` and unapproved (FIN-005) — the existing rule, not a copy;
   - the contact resolved by email as everywhere else (the client of record is the guest, not the agency);
   - the OPS-009 REQUEST_RESPONSE task and everything else the existing path already raises.
2. **No hold (default).** The request does not claim a cabin; the response says so in the API's words, and the portal repeats that sentence. If the client decides otherwise (README question), that becomes a separate change, not a flag bolted on here.
3. **Availability is checked on the server.** A departure that is sold out, closed or outside the sales calendar is refused with the same sentence the engine uses. Nothing is reserved between the check and the create.
4. **Their requests.** `GET /api/portal/requests` lists what this agency asked for, with the booking reference, status and what happens next in words. The team answers in the RMS; the portal never changes a request.
5. **Audit (P5).** History on the agency and on the booking, naming the agency user.
6. **RMS side.** Booking Requests shows these alongside engine requests, with the source marked as the portal and the agency named — check whether the existing source field covers it and extend it if not, in one line.

## Don't
- Don't duplicate the pricing, availability, cap or SLA logic.
- Don't let the portal set a price, a discount or a commission.
- Don't place a hold.

## Checks
- `composer check`.
- A request creates the same booking shape as an engine request, plus the agency and the frozen commission; the task is raised; the RMS list shows it with its source.
- An over-cap agency lands on ON_HOLD_AGENCY and the existing alert and task are raised.
- A sold-out or closed departure is refused; a request for another agency's departure scope is impossible by construction.
- The agency's history names the user.

## Report
Append **Task 03**: the endpoint and the action it reuses, the cap and SLA behaviour, the no-hold sentence, the RMS source, the audit. Git commands listed, not run.
