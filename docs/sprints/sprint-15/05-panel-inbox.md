# Task 05 · anakata-panel · Inbox page

**Repo:** anakata-panel · **Sprint:** 15 · **Needs:** Task 01 (API), Task 04 (types v0.16.0).

## Goal

Build `/crm/sales/inbox`, replacing the catch-all the same way Sprint 14's `journeys.vue` replaced it for that route. Flip the `inbox` nav item from `sprint: 'later'` to `sprint: 15` in `app/navigation/crm.ts`, and extend `guards.test.ts` the same way Sprint 14 Task 08 did for `journeys` (that task's REPORT is the pattern to copy: which describe block, which assertions).

## Repo check to do first

- Confirm `app/pages/crm/sales/inbox.vue` doesn't already exist and that the catch-all (`app/pages/crm/[group]/[item].vue`) is still what serves that route today.
- Confirm the current structure of `guards.test.ts` post-Sprint-14 (it was extended twice in that sprint, for journeys and then for segments/automations — read the current state, not Sprint 14's description of it, before adding a third case).
- Confirm whether `ContactDrawer.vue` already has a slot pattern this page's "open this conversation's contact" link should reuse (Sprint 14 added a Journeys section to that drawer; this page should link out to it the same way the journeys drawer linked to `/crm/sales/contacts?open={id}`, not duplicate contact details inline).

## Do

1. **List view.** `GET /api/crm/conversations`, paginated. One row per conversation: contact name (or raw address if unmatched — visually distinct, e.g. muted/italic, so staff can tell an unlinked thread from a known contact at a glance), subject, last-message preview, unread indicator, message count. Filter by status (open/closed) and unread. Unmatched conversations get a small "link to contact" affordance right on the row, not buried in the detail view, since that's the one action they need before anything else about them is useful.

2. **Thread view.** Opens from a row (drawer or dedicated panel — match whichever pattern `journeys.vue`'s enrolments drawer or a similar existing full-detail view uses, for consistency rather than inventing a third layout). Messages oldest-first, `IN`/`OUT` visually distinguished (the existing conversation-bubble styling pattern if the panel has one from elsewhere; if not, a simple left/right or border-color distinction is enough — this doesn't need new design system work).

3. **Reply.** A composer at the bottom of the thread view, plain text (matching Task 01's plain `body` input — no rich-text editor, same restraint Sprint 14 applied to template drafting). `POST .../reply`. On success, append the new message to the thread and clear the composer; don't require a full reload.

4. **Link-contact action.** For an unmatched conversation: a contact search (reuse `GET /api/crm/contacts?q=` the same way Sprint 14's template preview picker did) and `POST .../link-contact`.

5. **Permissions.** Anyone who can open CRM (`panel.crm`) can read and reply — there's no `rules.manage`-gated action here, unlike journeys/templates, since replying to a guest email isn't a business-rule change. State this explicitly in the REPORT as a deliberate difference from Sprint 14's pattern, not an oversight.

## Don't

- Don't build a compose-new-conversation button — Task 01 doesn't support starting a cold thread, so neither does this page.
- Don't show raw email headers or the full `body_html` unsanitized inline — render through whatever safe-HTML rendering the panel already uses elsewhere (check for a pattern before adding a new one; template preview in Sprint 14 rendered structured body content, but this is raw inbound HTML and needs actual sanitization, not the structured paragraphs/list/cta shape templates use).

## Checks

`pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`. Fresh-clone typecheck/build against the released `anakata-ui` tag.

Browser, both themes, after a seed reset: list shows conversations in last-message order; unread rows are visually distinct; opening a thread marks it read; reply sends and appears in the thread; an unmatched conversation can be linked to a contact and then shows that contact's name on the list.

## Report

Append **Task 05**: the layout choices and which existing patterns they reused, the permission difference from journeys/templates and why, what's not built (compose-new, attachments — carried over from Task 01's scope note). Git commands listed, not run.
