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

## Task 05 · CRM Inbox

Panel only. No API change. Types stay the `v0.16.0` aliases re-exported from `#anakata-ui/app/types`.

### Page

`/crm/sales/inbox` replaces the CRM catch-all for that URL. The nav item is sprint 15. B2B Partners stays `later`. `pageDecision` allows the path for `panel.crm`. A new case in `describe('seeded admin nav')` asserts that. The sprint 14 journeys case is unchanged.

The list is the contacts table: `table.list`, `.crm-filters`, `.list-pager`. Server order is `last_message_at` desc. Filters are status (`OPEN` / `CLOSED` / all) and unread (all / unread only). A row shows the contact name, or the raw `from` in italic when `contact_id` is null, plus subject, preview, message count, status, and last message. An unread row has a coral inset bar and an Unread pill.

The thread is a `USlideover`, the same shell as the journeys enrolments drawer. Messages stay oldest-first. `IN` sits left with a sand border, `OUT` sits right with a coral border. `body_html` goes in an `<iframe sandbox="">`, the same empty sandbox as template preview. Empty HTML falls back to `body_text` as text. `message_id` and `in_reply_to` are not shown.

A matched name links to `/crm/sales/contacts?open={id}`. The contact drawer is not duplicated here.

### Reply and link

The composer is a plain textarea. `POST` body is `{ message }`. The returned conversation replaces the open thread, so the new `OUT` line appears without a second GET, and the composer clears. The list row takes the new preview, count, and unread flag from that payload.

Unmatched rows have Link to contact on the row, and again in the drawer. Search is `GET /api/crm/contacts?q=` with the template picker's 300ms debounce. `POST …/link-contact` updates the row. If the response id differs (the shell was folded into an existing thread), the old row is removed and the survivor is kept. While the unread filter is on, a thread that becomes read drops off the list.

### Permissions

Reply and link are not behind `rules.manage`. `ConversationPolicy` allows every action for `panel.crm`, and the page follows that. Replying to a guest is not a business-rule change. This is a deliberate difference from journeys and templates.

### Not built

Compose-new and attachments stay out, as in Task 01. `PATCH { status }` exists and this page does not close or reopen a thread.

### Checks

`pnpm test` (49 files, 296 tests), `pnpm lint`, `pnpm typecheck`, and `pnpm build` passed against the sibling layer.

Fresh clone against `github:anakata-project/anakata-ui#v0.16.0` was not run. Task 04 left that tag unpushed, so a clone with no sibling layer cannot fetch it. The pin in `nuxt.config.ts` is already `#v0.16.0`.

### Browser

Signed in as Carolina (Admin). The local database had no `conversations` table yet, so `php artisan migrate` applied `2026_09_23_200001_create_conversations_tables` only. No seed reset. Seed data does not insert conversations. Two messages were posted to Mailpit (a known contact and `stranger.inbox@example.com`) and `queue:work --once` ran the poll. The poll also stored older Mailpit mail (booking summary, welcome, portal invite) as unmatched threads.

Dark theme, then light. List order was last message first. Unread rows showed the coral bar. Opening Cabin question cleared Unread on that row. The reply "The master cabin is free for that Sunday." appeared as `OUT`, the composer cleared, and the list preview and count (2) updated. Linking Unlinked hello to Anna Whitfield replaced the raw address with her name. Unmatched addresses stayed italic in both themes.

### Git commands

Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/assets/css/crm.css \
  app/components/crm/ConversationDrawer.vue \
  app/components/crm/ConversationLinkSearch.vue \
  app/navigation/crm.ts \
  app/pages/crm/sales/inbox.vue \
  app/types/api.ts \
  eslint.config.mjs \
  i18n/locales/en.json \
  tests/unit/guards.test.ts
git commit -m "$(cat <<'EOF'
Add the CRM inbox list, thread, and contact link.

Staff with panel access can read a thread, reply, and attach an unmatched message to a contact.
EOF
)"
```

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-15/REPORT.md
git commit -m "$(cat <<'EOF'
Record the CRM inbox page.

EOF
)"
```

## Task 06 · CRM B2B Partners page

Panel only. No API change. `B2bPartnerRow` is re-exported from `#anakata-ui/app/types`.

### Page

`/crm/sales/b2b-partners` replaces the CRM catch-all for that URL. The nav item is sprint 15. `pageDecision` allows the path for `panel.crm`. The Inbox guard test now expects B2B Partners at sprint 15 as well.

The list is `table.list` inside `.panel`. One row per agency from `GET /api/crm/b2b-partners`. Columns are name, status, contact, rate (`{n}%`), revenue, commission accrued, open deal count, and the `b2b_partner_activation` summary. Revenue and commission accrued go through `useMoney()`. Nothing is summed and there is no KPI row. The prototype columns the API does not return (type, network, terms, pending to pay, leakage, producing partners) are absent.

A row opens a `USlideover` that loads `GET /api/crm/b2b-partners/{id}`. Deal history is a read-only table of title, stage, `value_label`, and booking reference. The booking link is `/rms/reservations/bookings?open={reference}` and only when `can('panel.rms')`.

### Reused

`journeyHelpers.ts` groups journey-definition steps. It has no enrolment status, step, or next-due helper, so none was added. The list prints the same three fields the enrolments drawer prints: status pill, step name, `useDates().format(next_due_at, 'dateTime')`.

The one-enrolment block (key, status, step, next due, exit, sends, template link) lived inline in `ContactDrawer.vue`. That block is now `JourneyEnrolmentDetail.vue`. The contact drawer and this slideover both use it. Sends still link to `/crm/marketing/journeys?template=`.

"Open in RMS" is on each row and in the detail, only when `can('panel.rms')`. The target is `/rms/commercial/b2b?open={id}`, the same query the RMS agency screen and the contact drawer already use.

### Relationship lines

- Contact and enrolment: contact name links to `/crm/sales/contacts?open={id}`, plus status, step, and next due.
- Contact, `enrolment` null, `enrolment_note` null: the contact link, plus "CRM contact matched. b2b_partner_activation is not enrolled."
- No contact: the API sentence in `enrolment_note`, verbatim. No contact link.

### Write actions

None. No form, no approve, no rate edit, no `PATCH`.

### Seed

`DemoAgenciesSeeder` calls `ResolveContact` for every agency email, so every seeded agency has a CRM contact. The seeder does not dispatch `AgencyApproved`, so `b2b_partner_activation` is not enrolled from the fixture. After the current seed the page shows matched contacts and the not-enrolled line. It does not show `enrolment_note`. That row needs a later seed agency whose email matches no contact. No fixture was added in this task.

### Checks

`pnpm lint`, `pnpm typecheck`, `pnpm test` (49 files, 296 tests), and `pnpm build` passed against the sibling layer.

A fresh clone against `github:anakata-project/anakata-ui#v0.16.0` was not run. The pin in `nuxt.config.ts` is already `#v0.16.0`, used when `../anakata-ui` is absent.

### Browser

Signed in as Carolina (Admin). No seed reset. Dark and light.

Andes Luxe Travel (PENDING, P. Ibáñez, 10%, USD 0), Blue Latitude Travel (APPROVED, S. Ferreira, 10%, USD 46,550, USD 4,656), and Meridian Voyages (APPROVED, T. Nakamura, 15%, USD 26,600, USD 0). Each journey cell says the contact is not enrolled. The Andes drawer shows no deals, the same not-enrolled line, and Open in RMS. That link lands on `/rms/commercial/b2b?open=3` with the Andes Luxe Travel agency drawer. Row links are `open=3`, `open=1`, and `open=2`. Contact links are `/crm/sales/contacts?open={id}`. No write control on the page. The no-contact sentence was not on screen, for the seed reason above.

### Git commands

Do not run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/components/crm/B2bPartnerDrawer.vue \
  app/components/crm/ContactDrawer.vue \
  app/components/crm/JourneyEnrolmentDetail.vue \
  app/navigation/crm.ts \
  app/pages/crm/sales/b2b-partners.vue \
  app/types/api.ts \
  i18n/locales/en.json \
  tests/unit/guards.test.ts
git commit -m "$(cat <<'EOF'
Add the read-only CRM B2B Partners page.

Staff can see each agency's contact, ledger figures, and activation journey, and open the RMS record.
EOF
)"
```

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-15/REPORT.md
git commit -m "$(cat <<'EOF'
Record the CRM B2B Partners page.

EOF
)"
```

## Task 07 · Portal Pay Now

An agent can start a deposit or balance payment from their own request or booking. The portal opens Stripe's hosted page. It never collects card data.

### Portal inventory

`anakata-portal` is a Nuxt 4 SPA on port 3002 (`ssr: false`). It extends the sibling `anakata-ui` layer, and falls back to `github:anakata-project/anakata-ui#v0.16.0` when that sibling is absent. Scripts are `pnpm lint`, `pnpm typecheck`, `pnpm test`, and `pnpm build`. Pages call `useApi().request`. Types are re-exported from `#anakata-ui/app/types` in `app/types/api.ts`. Session state is `usePortalSession`. `auth.global` sends a signed-out visitor to `/login`. There is no per-booking permission helper. The lists are already this agency's rows.

Pages: `/login`, `/forgot`, `/accept`, `/reset-password`, `/`, `/rates`, `/availability`, `/requests`, `/requests/new`, `/bookings`, `/commissions`, `/materials`. There is no `[reference].vue`. A booking opens a `USlideover` (`data-booking-drawer`) on `/bookings`. `/requests` was a table only. Copy is English (`i18n/locales/en.json`). There was no Stripe code and no shared payment component in `anakata-ui`.

### Why the lists changed

`POST /api/portal/bookings/{booking}/payment-link` takes a numeric booking id. `PortalBookingResource` and `PortalRequestResource` returned `reference` and not `id`, and neither said whether a link was already open. The button could not be addressed or hidden.

Both resources now include `id` and `open_payment_kinds` (values of links with status `OPEN`). The request resource also includes `payment_state`, the same three sentences as bookings. Both indexes eager-load open payment links. The request index also uses `withChargesSummary()` and `withLedgerAggregates()`, which the booking index already used, so `paymentStateWords()` does not query per row. `Booking::openPaymentKinds()` reuses a loaded relation. No new route. Cancel stays staff-only. Stripe success and cancel URLs are unchanged.

A second open link of the same kind is still 422. The button is hidden when that kind is already in `open_payment_kinds`, so the agent is not sent into that refusal on the normal path.

### Pay Now

`portalPayKind` returns one kind, or nothing:

- `CANCELLED`, `CANCELLED_POSTPAID`, and `RELEASED` show nothing.
- `Awaiting deposit` without an open `DEPOSIT` link shows Pay deposit.
- `Deposit received` without an open `BALANCE` link shows Pay balance.
- `Paid in full` shows nothing.

`PortalPayButton` posts `{ kind }` and calls `window.location.assign` on `PaymentLink.url`. A 422 or 403 is shown with `portalPageMessages()`. There is no Stripe.js, no card field, and no cancel action. The booking slideover and a new request slideover (`data-request-drawer`) both use that button. The portal does not poll. Status updates when `/bookings` or `/requests` loads again.

`PaymentLink` and `PortalPaymentLinkInput` are re-exported from the portal's `app/types/api.ts`. `api.d.ts` went from 18374 lines to 18380. No `anakata-ui` tag. The github pin `#v0.16.0` does not include `id` or `open_payment_kinds` until a later release. Local typecheck uses the sibling layer.

### Checks

Pint on the touched PHP passed. Larastan on those files reported no errors. `php artisan test` passed for the new list assertion, the portal request key list, the OpenAPI schema test, and the bookings list query-count test.

Portal `pnpm lint`, `pnpm test` (42), `pnpm typecheck`, and `pnpm build` passed.

### Browser

The dev server was already signed in as Browser Check (Blue Latitude). No seed reset.

`/bookings` showed a REQUESTED row (Task Guest, awaiting deposit) and `ANK-2026-0007` (CONFIRMED, deposit received). The first drawer said Pay deposit. The confirmed drawer said Pay balance. The drawer had no `input` and no `iframe`. Pay deposit left the portal for `https://buy.stripe.com/test/plink_test_001`, the fake gateway's hosted URL. After reload, that request no longer showed Pay deposit. `ANK-2026-0007` still showed Pay balance. `/requests` opened `ANK-R-2026-0043` in the new drawer and hid Pay, because that row is the same booking and the link is now open. No other agency's reference was on either list.

That check wrote one open deposit link (`plink_test_001`) on the Task Guest booking in the local database.

### Git

Not run:

```bash
# anakata-api
git add \
  app/Http/Controllers/Portal/PortalBookingController.php \
  app/Http/Controllers/Portal/PortalRequestController.php \
  app/Http/Resources/Portal/PortalBookingResource.php \
  app/Http/Resources/Portal/PortalRequestResource.php \
  app/Models/Booking.php \
  docs/sprints/sprint-15/REPORT.md \
  tests/Feature/OpenApi/PortalResponseSchemasTest.php \
  tests/Feature/Portal/PortalPaymentLinkTest.php \
  tests/Feature/Portal/PortalRequestsTest.php
git commit -m "$(cat <<'EOF'
Expose booking id and open payment kinds on the portal lists.

The pay route needs the id, and the portal hides Pay when that kind is already open.
EOF
)"

# anakata-ui
git add \
  app/types/api.d.ts \
  app/types/portal.ts
git commit -m "$(cat <<'EOF'
Regenerate portal list types for pay.

EOF
)"

# anakata-portal
git add \
  app/components/PortalPayButton.vue \
  app/pages/bookings.vue \
  app/pages/requests/index.vue \
  app/types/api.ts \
  app/utils/portalPay.ts \
  i18n/locales/en.json \
  tests/components/portalBusiness.test.ts \
  tests/unit/portalPay.test.ts
git commit -m "$(cat <<'EOF'
Let an agent start a hosted deposit or balance payment.

The portal posts the existing payment-link route and opens the returned URL. It does not collect card data.
EOF
)"
```
