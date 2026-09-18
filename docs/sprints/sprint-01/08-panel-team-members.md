# Task 08 · anakata-panel · Admin → Permissions: team members, invitations, user history
**Repo:** anakata-panel · **Sprint:** 1 (read `../anakata-api/docs/sprints/sprint-01/README.md` first)
**Needs:** task 04 (management API) and task 07.

## Goal
The RMS page Admin → Permissions (`/rms/admin/permissions`) looks like the prototype's `v-perm` view. This task builds its **Team members** panel for real: list, invite, edit role, disable or enable, resend invitation, and each user's history in the prototype's drawer. The matrix panel above it is task 09.

## Read first
- `.cursor/rules/anakata-core.mdc`, `nuxt-app.mdc`, `panel.mdc`
- `prototype/rms_index.html`:
  - the `v-perm` view (both panels and their order)
  - the `table.list` styles
  - pills (`.pill p-conf` etc.)
  - `#drawer`, `#drawer h2`, `.bid`
  - `drHistory()` and the `.tl2` / `.tli` / `.tlt` / `.tlw` timeline styles
  - `.notice`, `.warnbox`, `.btn`, `.btn.o`, `.field`
- `screenshots/18-change-history.png`
- `../anakata-api/docs/sprints/sprint-01/REPORT.md`, task 04: endpoints, guardrails, history events

## Do
1. **Page.** `app/pages/rms/admin/permissions.vue` replaces the placeholder for this route.
   - It is reachable with `users.manage` **or** `roles.manage`.
   - It shows two `AnkPanel`s in the prototype's order: the matrix (task 09; for now a placeholder panel titled as in the prototype) and **Team members**.
   - A user with only `roles.manage` doesn't see Team members; one with only `users.manage` sees the matrix read-only (task 09).
2. **Team members table.** A `table.list` with the prototype's columns **User · Role · Flags · Status**, plus a right-aligned actions column in the prototype's small `.btn.o` style.
   - **User**: the name; the email beneath it in mono `--iv62`.
   - **Flags**: the role's `flags`, joined with ` · ` and lower-case exactly like the prototype (`director · finance`), or `—` when empty.
   - **Status** pills: `Active` → `p-conf` as in the prototype. `Invited` and `Disabled` use the closest existing prototype pills; name the ones you chose in the report.
   - Show `last_login_at` as a tooltip on the status pill ("Last sign-in 18 Sep 2026, 09:12"), in Galápagos time through `useDates()`.
   - Above the table: the search field (name or email) and filters for status and role, styled like the prototype's list filters. Also **＋ Invite user** (primary `.btn`).
   - Paginate with the API's pagination, styled like the prototype's list footers. If the prototype has no pager, use a minimal mono "Previous · 1–25 of 40 · Next".
3. **Invite.** A modal (the Nuxt UI modal themed by the layer, square, hairline) with Name, Email and Role (a select of roles from `GET /roles`) and a notice: "They'll receive an email to set their password. The link is valid for 7 days."
   - A 422 maps errors onto the fields.
   - Success: a toast, the list refreshes, and the new row shows `Invited`.
4. **Row actions**, shown per state and hidden when not allowed:
   - **Edit**: a modal with Name and Role.
   - **Disable**: a confirmation with an optional reason and the warning "They're signed out on their next action."
   - **Enable**.
   - **Resend invitation**: invited users only.
   - **History**: opens the drawer.
   - You cannot disable yourself or change your own role, so hide those actions on your own row. The server enforces this anyway (409).
   - Any other **409** (e.g. the last admin) shows the API's message in a `.warnbox` inside the modal, not only a toast.
5. **History drawer.** A reusable `HistoryDrawer` + `HistoryTimeline` in `app/components/history/`; bookings will reuse it in Sprint 4.
   - It reproduces the prototype drawer: 600px, slides from the right, `--forest`, a hairline left border, `h2` with the subject label, and `.bid` with the subject type.
   - Above the timeline, the prototype's note: "Every change — who, when, what and why. Entries can't be edited or deleted."
   - Entries are newest first, rendered like `drHistory()`:
     - `.tlt`: `<time> · <actor_label>`, in Galápagos time. Put `useDates().zoneLabel()` once, next to the note.
     - Then the sentence.
     - Then `Reason: …` in `.tlw` when present.
   - Load more with pagination ("Load older" mono link).
   - **Sentences.** The API returns events and diffs, not text. Map them in one place, `app/components/history/describe.ts`, with i18n keys. For example:
     - `user.role_changed` → "Role changed · Sales Exec → Manager"
     - `user.invited` → "Invited as Manager"
     - `user.activated` → "Invitation accepted"
     - `user.disabled` → "Disabled"
     - `role.updated` → "Permissions changed · added: …, removed: …"
   - An unknown event falls back to the event name plus a compact `before → after` summary. Never render raw JSON.
   - Unit-test `describe.ts`.
6. **Data.** All calls go through `useApi()` with the generated types (task 06). Every mutation refreshes the list. No optimistic updates.

## Out of scope
The role matrix and role CRUD (task 09). Bulk actions. CSV export.

## Acceptance criteria
- [ ] As Carolina:
  - Invite a user → the row shows Invited → the Mailpit link → the user sets a password and signs in → Carolina's list shows them Active with a last sign-in time.
  - Their history reads "Invited as …" then "Invitation accepted", in Galápagos time.
- [ ] Last-admin guard through the UI. Create (via the API or task 09) a role with only `panel.rms` + `users.manage`, invite and activate a user with it, and sign in as that user. Trying to disable Carolina, the only active admin, shows the API's 409 message in the modal.
- [ ] Mateo and Lucía can't reach the page, which task 07's guard already covers.
- [ ] The page matches the prototype's `v-perm` Team members panel in both themes.
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm build`, `pnpm test` pass.
- [ ] A "Task 08" section in `REPORT.md` covering:
  - the pill choices
  - the history sentence table
  - any element built without a direct prototype source
