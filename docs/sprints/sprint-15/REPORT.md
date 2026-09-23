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

## Task 02 · CRM B2B partners

Read-only. No new tables. `GET /api/crm/b2b-partners` and `GET /api/crm/b2b-partners/{agency}`. Nothing writes `agencies`, `commission_pct`, or money. `Contact::viewAny` (`panel.crm`) authorises both. `AgencyPolicy` is `panel.rms` and is not used.

`ContactDerived` did not resolve an agency to a contact. The matcher that enrols `b2b_partner_activation` was private `JourneyEngine::agencyContact()`. That body is now `ContactDerived::contactForAgency()`: `Contact::normalizeEmail()` on the agency email, then `LOWER(contacts.email)`. The engine calls it. A missing contact is not created. The Agent lifecycle SQL is unchanged.

### Fields

This page computes nothing new.

| Field | Origin |
|---|---|
| `id`, `reference`, `name`, `status` | `agencies` |
| `commission_pct` | `agencies.commission_pct` |
| `contact.id`, `contact.name` | `contacts`, via `ContactDerived::contactForAgency()`. Null when no email match. |
| `revenue` | `AgencyBookingWindow::stats()` on the agency's bookings. Sum of `bookings.total`. Same helper `AgencyResource` uses when no date window is set. |
| `commission_accrued` | Same helper. Sum of `Booking::commissionAmount()` where `commission_approved` is true and there is no payout. |
| `enrolment` | `journey_enrolments` for journey key `b2b_partner_activation` on the matched contact. Step name and position from `journey_steps`. `next_due_at` from the enrolment. The list returns status, step, and next due. Show returns `CrmJourneyEnrolmentResource` (sends, exit, booking). |
| `enrolment_note` | Not a column. Set only when `contact` is null: "No CRM contact matches this agency, so b2b_partner_activation was not enrolled." |
| `open_deal_count` | `deals` whose `booking_id`, or any booking in `group_id`, has `bookings.agency_id`. Projected stage is `DealStages::stageSql()`. Counted only when that stage is in `DealStage::open()` (`NEW_LEAD`, `QUALIFYING`, `QUOTED`, `NEGOTIATION`). `deals` has no `agency_id`. An unbound deal has no agency, so it does not count. |
| `deals` (show) | That same agency link, newest `stage_entered_at` first, each row `DealDrawer::for()` through `DealResource`. |

### No contact

An agency with no matching CRM contact is on the list. Contact fields are null, enrolment is null, and `enrolment_note` says the journey never enrolled. Sprint 14 does not create a contact and does not enrol in that case. Hiding the row would hide the skip. A contact that exists but was never enrolled has `enrolment: null` and `enrolment_note: null`.

### Production KPI

Not built. The prototype's "2 producing partners" count has no rule. "Has a booking in the last 12 months" would be a guess. This waits on a business definition of producing, the same way Sprint 14 declined to invent journey conversion rates. Revenue and commission accrued stay the RMS ledger figures above. The list has no `meta.kpis`.

### PATCH

`PATCH /api/crm/b2b-partners/{agency}` is **405**, not 404 and not 403. The GET route occupies that URI, so Laravel reports the method as not allowed. `commission_pct` is unchanged. There is no write action on this controller.

### Git

Not run:

```bash
git add \
  app/Http/Controllers/Crm/B2bPartnerController.php \
  app/Http/Resources/Crm/B2bPartnerResource.php \
  app/Support/Crm/ContactDerived.php \
  app/Support/Journeys/JourneyEngine.php \
  docs/sprints/sprint-15/REPORT.md \
  routes/api/crm.php \
  tests/Feature/Crm/B2bPartnersTest.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php

git commit -m "$(cat <<'EOF'
Add the CRM B2B partner read model.

EOF
)"
```

## Task 03 · Portal-initiated payment links

`CreatePaymentLink::handle()` takes a staff `User`. The portal authenticates an `AgencyUser` on the `agency` guard, so that action cannot be the portal door. `CreatePortalPaymentLink` is a second door onto the same Stripe call.

The shared work (booking-status guard, open-link guard, amount guard, `StripeGateway::createPaymentLink`, `PaymentLink` insert) lives in `IssuePaymentLink`. `CreatePaymentLink` still records `payment_link.created` with the staff user. The portal action does not call `CreatePaymentLink`.

### Ownership

The action throws `AuthorizationException` unless `bookings.agency_id` is set and equals the agency user's `agency_id`. Another agency's booking and a D2C booking (`agency_id` null) are 403. An unknown id stays the route's 404. This is the same `agency_id` filter `PortalBookingController::index` already uses.

The body is `{ kind }` only (`DEPOSIT` or `BALANCE`). An `amount` in the body is not a validated field, and the action passes only `kind` into the issuer, so the amount is always `depositAmount()` or `balanceFresh()`.

A second open link of the same kind is 422 on `kind`, the same refusal `CreatePaymentLink` already returns. The task text also said "return the existing link". Sprint 5's staff test refuses the duplicate. The portal matches that.

### History

`change_history.actor_id` is a foreign key to `users`. `History::record()` types its actor as `?User`. An `AgencyUser` cannot be that actor, and no migration was added. The portal row uses `actorLabel` `{name} via portal`, `actor_id` null, and `agency_user_id` in context. `source` is already `portal` for `/api/portal/...`.

`payment_links.created_by` is the same staff foreign key. The action switches the default auth driver to `web` for the transaction (the `SubmitPortalRequest` pattern) so `HasAuditColumns` does not write the agency user id into `created_by`. The column stays null. The RMS booking history timeline already prints `actor_label`, so a portal link shows `{name} via portal` and a staff link stays the staff name. The payments tab does not label a creator. No panel change.

### Settlement

`ProcessStripeEvent` loads the link by `stripe_id`. A portal-created link settles through the existing webhook. That pipeline was not edited. Cancelling a link stays `CancelPaymentLink` (staff only).

### Git

Not run:

```bash
git add \
  app/Actions/Payments/CreatePaymentLink.php \
  app/Actions/Payments/CreatePortalPaymentLink.php \
  app/Actions/Payments/IssuePaymentLink.php \
  app/Http/Controllers/Portal/PortalBookingController.php \
  app/Http/Requests/Portal/CreatePortalPaymentLinkRequest.php \
  docs/sprints/sprint-15/REPORT.md \
  routes/api/portal.php \
  tests/Feature/Portal/PortalPaymentLinkTest.php

git commit -m "$(cat <<'EOF'
Let an agency user open a payment link for their own booking.

EOF
)"
```

## Task 04 · Regenerate types, release v0.16.0

Types only. `package.json` and the top changelog entry were both `0.15.0`, so the bump is `0.15.0` → `0.16.0`. Pins in the panel, engine, and portal were `#v0.15.0` in each `nuxt.config.ts` and `README.md`. All six now say `#v0.16.0`. The layer README example that still showed `#v0.12.0` now shows `#v0.16.0`. `pnpm types:api` regenerated `app/types/api.d.ts` from `http://localhost:8000/docs/api.json`. That file was not edited by hand.

### Prelude

Inspection of the live spec, before any edit:

- `CrmConversationResource.messages` already `$ref`s `CrmMessageResource`. `body_html` and `body_text` are strings. `to` is `string[]`. Those were left alone.
- `POST /api/portal/bookings/{booking}/payment-link` already returns `PaymentLinkResource` and validates `CreatePortalPaymentLinkRequest`. No portal payment-link resource.
- The B2B schema name is `B2bPartnerResource`. There is no `CrmB2bPartnerResource`. The reply body is `ReplyToConversationRequest`. There is no `ReplyConversationRequest`.
- `contact`, enrolment `step`, and enrolment `sends` already had real object shapes.

The prelude only typed the gaps. JSON values are unchanged (backed enums encode as their string). Nested `DealResource` instances still serialise through `jsonSerialize`.

- Conversation `status` is `ConversationStatus` (`OPEN` | `CLOSED`) via a private method, the same pattern as journey enrolment `status`. The schema already existed on `UpdateConversationRequest`. The resource was a plain string.
- Message `direction` is `MessageDirection` (`IN` | `OUT`). That schema did not exist.
- Partner `status` `$ref`s the existing `AgencyStatus`. Enrolment `status` on both the summary arm and the full arm `$ref`s the existing `JourneyEnrolmentStatus`.
- Detail `deals` was `list<array<string, mixed>>`, which Scramble emitted as an array of empty arrays. It is now `list<DealResource>`. The list arm still has no `deals` key.

`CrmResponseSchemasTest` and `PortalResponseSchemasTest` assert the new refs, the message body strings, both enrolment arms, and that one B2B arm has `deals` and the other does not. Those two tests, plus `B2bPartnersTest` and `ConversationsTest`: 18 passed (732 assertions). Pint passed on the five files. Larastan is clean on the three resources.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 17860 | 18374 |
| `app/types/crm.ts` | 253 | 261 |
| `app/types/portal.ts` | 25 | 26 |
| `app/types/index.ts` | 435 | 443 |

### Schema → alias

| Alias | Source |
|---|---|
| `Conversation` | `CrmConversationResource` |
| `ConversationMessage` | `Conversation['messages'][number]` |
| `ConversationStatus` | named schema |
| `MessageDirection` | named schema |
| `ConversationReplyInput` | `ReplyToConversationRequest` |
| `ConversationLinkInput` | `LinkConversationContactRequest` |
| `B2bPartnerRow` | `B2bPartnerResource` |
| `PortalPaymentLinkInput` | `CreatePortalPaymentLinkRequest` (`portal.ts`) |

`ConversationLinkInput` is not in the task table. Task 05 posts link-contact, so the alias ships in this release. There is no export named `Message` or `Conversation`. `MessageTemplate` stays the template alias. The created link stays the existing `PaymentLink` alias in `payments.ts`. `portal.ts` does not import it.

### Leftovers

`messages` is required on `CrmConversationResource`. The index omits the key unless the relation is loaded. The PHPDoc already marks it optional (`messages?:`). Scramble still requires it. The show, reply, link, and status routes load the relation. No second type was written.

`B2bPartnerRow` is the list/detail union. Only the detail arm has `deals`. `enrolment` is the full object, the summary object, or null. `enrolment_note` is the constant sentence or null, not a free string.

`UpdateConversationRequest` exists (`status: ConversationStatus`). Task 05 does not close a thread, so it has no alias.

`PaymentLinkResource.kind` and `status` stay strings. `PaymentLinkStatus` stays the hand-written union already in `payments.ts`.

### Checks

Layer `pnpm lint`, `pnpm typecheck`, `pnpm test` (35) and `pnpm build` passed. Panel, engine, and portal `pnpm typecheck` and `pnpm build` passed against the sibling layer.

Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine,anakata-portal}`, working trees overlaid (no `node_modules`). The ui clone is **0.16.0**. Panel, engine, and portal resolve the sibling layer, so they do not fetch `#v0.16.0`. `pnpm typecheck` and `pnpm build` passed in all four.

- ui / panel / engine / portal: typecheck pass
- ui / panel / engine / portal: build pass
- **OVERLAY CLONE OK**

The tag is not pushed. The after-push clone was not run. Repeat the clone after the commands below, checking out `anakata-ui` at `v0.16.0` with no overlay.

### Git

Not run:

```bash
# anakata-api
git add \
  app/Http/Resources/Crm/B2bPartnerResource.php \
  app/Http/Resources/Crm/CrmConversationResource.php \
  app/Http/Resources/Crm/CrmMessageResource.php \
  docs/sprints/sprint-15/REPORT.md \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/Feature/OpenApi/PortalResponseSchemasTest.php

git commit -m "$(cat <<'EOF'
Type the inbox, B2B partner, and portal payment-link schemas.

EOF
)"

# anakata-ui
git add \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/crm.ts \
  app/types/index.ts \
  app/types/portal.ts \
  package.json

git commit -m "$(cat <<'EOF'
Release v0.16.0 with inbox, B2B, and portal payment-link types.

EOF
)"

git tag v0.16.0
git push origin HEAD
git push origin v0.16.0

# anakata-panel, anakata-engine, anakata-portal
git add README.md nuxt.config.ts

git commit -m "$(cat <<'EOF'
Pin anakata-ui v0.16.0.

EOF
)"
```
