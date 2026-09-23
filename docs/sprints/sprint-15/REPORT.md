# Sprint 15 · Report

## Task 01 · Inbound email, conversations, and replies

Staff can read a shared email inbox and reply from the CRM. Inbound mail is polled. Outbound replies use the mailer Sprint 7 already shipped. Nothing writes to `deliveries`.

### Poll, not a Graph webhook

Sprint 7 did not ship a Graph client. Production mail is SMTP (`MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD`). Local, testing, and e2e use Mailpit. There is no public HTTPS callback and no Graph subscription to renew (mail subscriptions die within 3 days). A webhook cannot be exercised in Docker.

`anakata:inbox-poll` runs every minute (`withoutOverlapping`, `onOneServer`) and queues `PollInboxJob`. The job walks pages, newest first, and stops at an already-stored `message_id` or an empty page (20 pages max).

- `INBOX_DRIVER=mailpit` (the default): `GET {MAILPIT_URL}/api/v1/messages` and the per-message endpoint. This is what a later e2e inject helper can write into.
- `INBOX_DRIVER=graph`: `GraphMailbox` takes a client-credentials token (`GRAPH_TENANT_ID`, `GRAPH_CLIENT_ID`, `GRAPH_CLIENT_SECRET`, mailbox `GRAPH_MAILBOX` or `MAIL_FROM_ADDRESS`) and reads `GET /users/{mailbox}/mailFolders/inbox/messages`. Application permission `Mail.Read`. Keys are empty in `.env.example`. If the driver is `graph` and any key is empty, the command logs and skips.

Outbound replies stay on Laravel Mail. `From` is `MAIL_FROM_ADDRESS`. `Reply-To` is `IssuerMail::replyTo()` (legal-entity email). There is no Graph send transport. Production inbound stays dark until TEC-002 keys exist. No IMAP.

### Schema

One migration.

`conversations`: nullable `contact_id`, normalised `subject` (`Re:` / `Fwd:` / `Fw:` stripped), `last_message_at`, `status` (`OPEN` | `CLOSED`), `unread`, audit columns. Index `(contact_id, last_message_at)`.

`messages`: `conversation_id`, `direction` (`IN` | `OUT`), `from`, `to` (JSON list), `subject`, `body_html` (full), `body_text` (quoted reply and signature stripped), `message_id` (unique, nullable in the column), `in_reply_to`, `sent_at`, nullable `staff_id`, audit columns.

Morph map: `conversation`, `message`.

**Missing `Message-ID`.** MySQL unique indexes allow many NULLs, so a header-less message is stored as `missing:{sha256 of from|sent_at|subject|body}` and the unique key still dedupes a re-poll.

**Threading.** `In-Reply-To` and `References` first, matched against `messages.message_id`. Otherwise the same normalised subject for the same contact. An unmatched sender threads only with other unmatched mail from that same address and subject, so two strangers both writing "Hello" stay apart. A new conversation starts `OPEN` and unread. A new inbound on an existing thread sets `unread` and moves `last_message_at` forward. It does not reopen a `CLOSED` thread.

**Quoted text.** `body_text` drops lines from `On … wrote:`, `-----Original Message-----`, a `--` signature, and `>` quotes. `body_html` is stored whole. Attachments are not downloaded.

### Matching

`Contact::normalizeEmail()` then `contacts.email`, then `currentSurvivor()`. Read-only. `ResolveContact` is not called, so an unknown address does not insert a contact, does not write `contact.created`, and does not fill phone or type. The thread stays unlinked until `POST …/link-contact`.

### Reply and the other writes

`SendConversationReply` (staff, `panel.crm` only — no new permission). It requires an existing conversation and a latest inbound `from`. It writes one `OUT` row with `staff_id`, sets `In-Reply-To` and `References` to that inbound `message_id`, generates our own `Message-ID`, marks the thread read, and queues `ConversationReplyMail` after the transaction commits. History `conversation.replied`. No `deliveries` row.

`CaptureInboundMessage` writes `conversation.message` as System. A duplicate `message_id` returns the existing row and writes nothing.

`PATCH` accepts `{ status }` of `OPEN` or `CLOSED` only (422 otherwise). The same status writes no history. Anything else is `conversation.status_changed`.

`GET …/{id}` marks the thread read (`conversation.read` only when `unread` flips). Task 05 opens a thread to clear the flag, and this task's routes have no other mark-read call besides reply. `PATCH` stays status-only.

`POST …/link-contact` is 422 when the thread is already linked. If that contact already has a conversation with the same normalised subject, the messages move onto it and the empty shell is deleted (`conversation.linked`, `absorbed_conversation_id`). Otherwise `contact_id` is set.

### Timeline

`ContactTimeline` unions `messages` whose conversation has a `contact_id`. Kind `conversation.message`. Title is "Email received" or "Reply sent". Detail is the subject. Link `{ type: conversation, id }`. No body. An unlinked thread is on nobody's timeline; linking makes the existing messages show up, because the timeline is a live query.

### Not built

Attachments, compose-new, a Graph webhook or subscription renewal, IMAP, WhatsApp (TEC-005), and any write to `deliveries`.

### Git

Not run:

```bash
git add \
  .env.example \
  app/Actions/Crm/CaptureInboundMessage.php \
  app/Actions/Crm/LinkConversationContact.php \
  app/Actions/Crm/MarkConversationRead.php \
  app/Actions/Crm/SendConversationReply.php \
  app/Actions/Crm/UpdateConversationStatus.php \
  app/Console/Commands/InboxPollCommand.php \
  app/Enums/ConversationStatus.php \
  app/Enums/MessageDirection.php \
  app/Http/Controllers/Crm/ConversationController.php \
  app/Http/Requests/Crm/IndexConversationsRequest.php \
  app/Http/Requests/Crm/LinkConversationContactRequest.php \
  app/Http/Requests/Crm/ReplyToConversationRequest.php \
  app/Http/Requests/Crm/UpdateConversationRequest.php \
  app/Http/Resources/Crm/CrmConversationResource.php \
  app/Http/Resources/Crm/CrmMessageResource.php \
  app/Jobs/PollInboxJob.php \
  app/Mail/Crm/ConversationReplyMail.php \
  app/Models/Conversation.php \
  app/Models/Message.php \
  app/Policies/ConversationPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Support/Crm/ContactTimeline.php \
  app/Support/Mail \
  app/Support/Schedule/AnakataSchedule.php \
  config/anakata.php \
  config/services.php \
  database/migrations/2026_09_23_200001_create_conversations_tables.php \
  docs/sprints/sprint-15/REPORT.md \
  routes/api/crm.php \
  tests/Feature/Crm/ConversationsTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/Unit/Support/Mail/InboxThreadingTest.php

git commit -m "$(cat <<'EOF'
Add the CRM inbox: polled inbound mail, threaded conversations, and staff replies.

EOF
)"
```
