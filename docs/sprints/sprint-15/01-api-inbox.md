# Task 01 · anakata-api · Inbound email, conversations, and replies

**Repo:** anakata-api · **Sprint:** 15 · **Needs:** Sprint 7 (Graph mailer, `deliveries`), Sprint 9 (contacts, identity resolution).

## Goal

The CRM's Inbox tab (`nav.crm.inbox`, still `sprint: 'later'`) needs real messages to show. Today the codebase sends mail out through a Graph mailer to a single configured mailbox; nothing comes back in. This task adds inbound capture on that same mailbox, a `conversations` / `messages` schema that threads inbound and outbound together per contact, and a reply endpoint so staff can answer from the panel instead of a separate mail client.

Email only. WhatsApp stays TEC-005.

## Repo check to do first

Before writing code, confirm against the current tree (this was not independently re-verified for this task file and must be):

- The exact Graph mailer implementation from Sprint 7 (`docs/sprints/sprint-07/03-api-email-delivery.md` names the pattern; find the actual class under `app/Mail` or `app/Support` that Sprint 7's REPORT records, since this task reuses its client-credentials auth rather than re-deriving it).
- Whether Microsoft Graph's `/subscriptions` webhook (change notifications on the mailbox's `Inbox` folder) or a polling job against `/me/messages` fits better given the existing Graph HTTP client setup. A webhook needs a public HTTPS callback and a renewal job (Graph subscriptions expire in ≤ 3 days for mail resources); polling needs no public endpoint but adds latency. Record the choice and why in the REPORT — don't silently pick one.
- `app/Models/Contact.php` and `App\Actions\Contacts\ResolveContact` (Sprint 9) for the exact matching rule inbound mail should reuse.

## Do

1. **Schema.** Two tables, one migration.
   - `conversations`: `contact_id` (nullable — an inbound message from an unrecognised address creates no contact), `subject`, `last_message_at`, `status` (`OPEN` | `CLOSED`), `unread`, audit columns. One conversation per `(contact_id, normalised subject thread)` — thread by the `References` / `In-Reply-To` headers first, falling back to a normalised subject (strip `Re:`/`Fwd:` prefixes) when those headers are absent, matching how most mail clients thread.
   - `messages`: `conversation_id`, `direction` (`IN` | `OUT`), `from`, `to` (JSON list), `subject`, `body_html`, `body_text`, `message_id` (the RFC 5322 `Message-ID`, unique, nullable for very old inbound mail that lacks one), `in_reply_to` (nullable), `sent_at`, `staff_id` (nullable, set on outbound), audit columns.
   - Morph map entries: `conversation`, `message`.

2. **Inbound capture.** A job (queued, idempotent on `message_id`) that:
   - Fetches new mail from the configured mailbox via whichever mechanism the repo-check above settles on.
   - Matches the `From` address to an existing contact via the same normalised-email lookup `ResolveContact` uses for the engine — **read-only**: an unmatched sender does not create a contact. That's a deliberate difference from `ResolveContact`, which creates on the engine side; here an unrecognised inbound address is an unlinked conversation a staff member can attach to a contact manually later, not an auto-created lead.
   - Strips quoted reply text and signature blocks best-effort for `body_text` (store the full raw `body_html` always; the stripped text is what card previews and the drawer show first, matching the CRM's existing timeline pattern of a short first-line preview).
   - Threads into an existing `conversations` row when the threading rule above matches, otherwise opens a new one and sets `unread = true`.

3. **Outbound reply.** `App\Actions\Crm\SendConversationReply`, staff-only: takes `conversation_id`, `body`, sends through the same Graph/SMTP mailer Sprint 7 configured (same sending mailbox, same `Reply-To`), writes a `messages` row with `direction = OUT`, `staff_id`, sets `In-Reply-To` / `References` to the last inbound message's `message_id` so the guest's client threads it correctly, and does **not** write to `deliveries` — this is ad hoc correspondence, not a templated automation or document send, so it doesn't belong in the delivery ledger Sprint 7 built for those.

4. **Endpoints**, under the existing `panel.crm` group, same shape as `ContactController` / `DeliveryController`:
   - `GET /api/crm/conversations` — paginated, newest `last_message_at` first, filters: `status`, `unread`, `contact_id`. Each row: contact name (or the raw `from` address when unmatched), subject, last message preview, unread flag, message count.
   - `GET /api/crm/conversations/{id}` — the conversation with every message, oldest first.
   - `POST /api/crm/conversations/{id}/reply` — body `message`; runs `SendConversationReply`; marks the conversation read.
   - `PATCH /api/crm/conversations/{id}` — `{ status }` only (open/close), same confirm-before-write pattern as the journey active toggle.
   - `POST /api/crm/conversations/{id}/link-contact` — attach an unmatched conversation to a contact (`contact_id` in body), for the case where inbound capture couldn't resolve one automatically.

5. **Contact timeline.** A new inbound or outbound message on a linked conversation writes a `conversation.message` row to `ContactTimeline`, one line, no body text (the drawer's Inbox link opens the full thread — don't duplicate the message content into the timeline).

## Don't

- Don't build a compose-new-conversation flow. This task threads existing correspondence; starting a cold outbound conversation that isn't a reply to something inbound is out of scope (staff already have journeys and manual document sends for that).
- Don't attach files. Inbound attachments are dropped this sprint; note the gap in the REPORT rather than half-building storage for them.
- Don't touch `deliveries`. Conversations are a parallel, separate log — see point 3 above.

## Tests

- Inbound mail from a known contact's address threads onto their existing conversation when `References` matches; a cold inbound with no matching header opens a new conversation.
- Inbound mail from an address that matches no contact creates an unlinked conversation, not a new contact.
- A reply sets `In-Reply-To`/`References` correctly and is queued through the existing mail transport (a faked HTTP client for Graph, Mailpit for SMTP, same pattern Sprint 7 used).
- Re-processing the same inbound `message_id` (a redelivered webhook notification, or a poll that re-lists an already-seen message) does not create a duplicate `messages` row.
- `PATCH .../{id}` with a status other than `OPEN`/`CLOSED` is 422.

## Report

Append **Task 01**: the webhook-vs-poll decision and why, the schema, the matching rule and why an unmatched sender doesn't auto-create a contact (deliberate difference from `ResolveContact`), the threading rule, what's explicitly not built (attachments, compose-new). Git commands listed, not run.
