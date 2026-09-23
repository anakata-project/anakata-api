# Task 04 · anakata-ui · Regenerate types, release v0.16.0

**Repo:** anakata-ui · **Sprint:** 15 · **Needs:** Tasks 01–03 (done; REPORT sections exist), the API on port 8000 with a fresh seed.

Same recipe as Sprint 14 Task 06: inspect `/docs/api.json`, one API prelude for any resource Scramble still emits with a gap, schema tests, `pnpm types:api` (never hand-edit `api.d.ts`), aliases, then the release. Confirm the current version before assuming the bump — the last confirmed release was v0.15.0; do not assume nothing shipped between then and now without checking `package.json` and `CHANGELOG.md` directly, the same correction Sprint 14 Task 06 had to make against a stale assumption.

## Repo check to do first

- Current `package.json` version and top `CHANGELOG.md` entry, to confirm the correct bump (expected v0.15.0 → v0.16.0, but confirm rather than assume).
- Inspect the live spec for `CrmConversationResource`, `CrmMessageResource`, `CrmB2bPartnerResource`, and the portal's new payment-link endpoint's response shape, the same way Sprint 14 Task 06 inspected the seven CRM resources before writing the prelude — most likely gaps: array-typed nested objects (`messages` on a conversation, `body_html`/`body_text` fields), and any new enum (`conversation status`, `message direction`) returned as `->value` instead of the enum.
- Confirm whether `CreatePortalPaymentLink`'s response reuses the existing `PaymentLink` resource as-is (likely, per Task 03) or needs a portal-specific resource — if it's the same resource, no new schema is needed there at all.

## Do

1. **Inspect, then one API prelude** for whatever gaps the inspection actually finds — don't assume; Sprint 14's Task 06 repo-check found several of its "expected" gaps didn't exist (`AutomationResource`'s object was already complete) and some it hadn't expected did. Extend `CrmResponseSchemasTest.php` and the portal's equivalent schema test (confirm its name — it wasn't touched in Sprint 14, so cite it directly from the current tree rather than guessing) to assert the new resources aren't empty and their nested objects have real shapes.

2. **Regenerate.** `pnpm types:api` with the API up. Record `wc -l` before/after for `api.d.ts`, `crm.ts`, and any new alias file. Do not hand-edit the generated file.

3. **Aliases**, `crm.ts` (and a portal-facing file if the portal has its own alias layer — confirm its structure first, since it wasn't inspected in any prior sprint's repo-check available here):
   | Alias | Source |
   |---|---|
   | `Conversation` | `CrmConversationResource` |
   | `ConversationMessage` | `Conversation['messages'][number]` |
   | `ConversationReplyInput` | `ReplyConversationRequest` (confirm exact FormRequest name against the tree) |
   | `B2bPartnerRow` | `CrmB2bPartnerResource` |
   | `PortalPaymentLinkInput` | whatever FormRequest Task 03's endpoint validates against |

   Check for name collisions the way Sprint 14 Task 06 found the `Segment` collision — `Message` in particular is a generic enough name to check against existing exports before assuming it's free.

4. **Release and pins.** Version bump per the repo-check above. `CHANGELOG.md`. Pin `#v0.16.0` in the panel's and portal's `nuxt.config.ts` and `README.md` — confirm current pins first rather than assuming they still match what Sprint 14 left (`panel #v0.15.0`, `engine #v0.15.0` after Sprint 14's release, per that sprint's Task 06; the portal's pin was never confirmed in any material available for this task and must be checked directly).

## Checks and report

Same sequence as Sprint 14 Task 06: layer checks, then panel/engine/portal typecheck and build against the sibling layer, then a fresh clone with working trees overlaid. Append Task 04 to `REPORT.md`: prelude, schema → alias table, leftovers, line counts, clone results, git commands (not run).
