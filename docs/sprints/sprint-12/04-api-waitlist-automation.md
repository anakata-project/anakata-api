# Task 04 · anakata-api · Waitlist automation
**Repo:** anakata-api · **Sprint:** 12 · **Needs:** task 01.

## Goal
R-B5 without anyone watching the calendar: when a cabin frees, the first entry in line hears about it, once, automatically — and nothing is held for them (O4).

## Read first
- `docs/requirements/08-dev-decisions.md`: **O4**, and G7 (the waitlist table; this supersedes only its automatic case), A4, J5, J6, N1, M2
- doc 03 R-B5, doc 01 §4.4 (never overbook), doc 06 item 4
- `waitlist_entries`, `AddWaitlistEntry`, `NotifyWaitlistEntry`, `RemoveWaitlistEntry`, `AvailabilityChanged`, `HoldExpired`, `CabinClaim`, `EngineLabel` (the LIMITED state already exists)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **What counts as a cabin freeing.** One SQL predicate over computed availability: a departure has a free cabin in a category where an un-notified, un-removed waitlist entry exists. Reached from the events that can change it (`AvailabilityChanged`, `HoldExpired`, `BookingStatusChanged` to a cancelled or released status, a block lifted) through a queued listener, and from a sweep for the cases no event covers.
2. **The sweep.** `anakata:waitlist-notify`, every fifteen minutes, with the run hooks. For each departure with free capacity and waiting entries: take the entries in FIFO order (`created_at`, then id), and notify as many as there are free cabins in that category, one message each. An entry already notified for that departure is never notified again; `notified_at`, `notified_by` (null for the system) and `notified_channel` record it, as the manual action does.
3. **The message.** Delivery kind `WAITLIST_OFFER` (J5 key `waitlist:{entry}`), email only (WhatsApp is TEC-005), English: the departure, the cabin category, a link to the engine's departure page, and the sentence that cabins are first-come and nothing is held. Transactional — the guest asked to be told — so no consent gate (M2), and the REPORT says why. No address on the entry's contact means one blocked delivery with that key.
4. **Nothing is held (O4).** No claim, no hold, no booking. A test asserts the claim count is unchanged by a notification round.
5. **Staff.** `NotifyWaitlistEntry` stays as it is for a by-hand notice. The list payload gains `auto_notified` (whether the system sent it) and the entry's position in line, computed in SQL. Removing an entry stops it from being considered.
6. **Alerts and tasks.** No new alert kind: a notified entry that has not booked is sales work, not a system fault. Raise CRM task kind `WAITLIST_FOLLOW_UP` (M6, key `waitlist-follow-up:{entry}`, owner the departure's… — use the booking-less rule: unassigned, needs `bookings.create`, due 2 business days after the notice), closed when the entry is removed, books, or is completed by hand.

## Don't
- Don't place a claim or hold for a waitlisted guest.
- Don't notify the same entry twice for one departure.
- Don't change the engine's LIMITED state; it already exists.

## Checks
- `composer check`.
- A cancelled booking frees a cabin → the first entry is notified once; a second free cabin → the next entry; a third round with no free cabin → nothing.
- Replay of the event and a re-run of the sweep send nothing new.
- The claim count and every booking status are unchanged by a round.
- No email address → one blocked delivery; removal stops consideration.
- The follow-up task is raised once and closes on removal.

## Report
Append **Task 04**: the predicate, the listener and sweep, the message and its key, what is deliberately not held, the staff fields, the follow-up task. Git commands listed, not run.
