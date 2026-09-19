# BR-06 · Who can see Business Rules
- **Tags:** sprint-2, config
- **Priority:** P2
- **Users:** Mateo · Lucía
- **Start:** reset

## Why
`rules.view` is Admin-only. Nav and URL must agree.

## Steps
1. Sign in as Mateo. Confirm the sidebar. Type `/rms/admin/business-rules`.
2. Sign out. Sign in as Lucía. Same URL.

## Expected
- [ ] E1 · Mateo: no **Business Rules** nav item. Direct URL → `/rms/reservations/calendar` + toast `You don't have permission to do that.`
- [ ] E2 · Lucía: same.
