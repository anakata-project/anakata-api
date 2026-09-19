# ROLE-01 · Grant, use, revert a permission
- **Tags:** sprint-1, users-roles
- **Priority:** P1
- **Users:** Carolina · Lucía
- **Start:** reset
- **Needs:** two browser contexts

## Why
Permission changes apply on the next request, not on the cookie. History must name the catalogue label.

## Steps
1. Context B: sign in as `lucia@anakata.test` / `password`. Stay signed in on Calendar.
2. Context A: sign in as `carolina@anakata.test` / `password`. Open `/rms/admin/permissions`.
3. In the **Sales Exec** column, find the row **Delete reservation** (`bookings.delete`). Toggle it from `✗ No` to granted (`✓ Yes`). Click `Save` on the sticky bar.
4. Open **History** on the Sales Exec role.
5. Context B (Lucía): trigger a new request (navigate to `/rms/reservations/bookings` and back, or reload). Lucía now has the permission on the next request — there is no Delete button on Calendar yet, so treat “has it” as: the API `/api/auth/me` permissions list includes `bookings.delete` (open the network panel) **or** any UI that appears once she has it.
6. Context A: toggle **Delete reservation** back to `✗ No`. `Save`. Open History again.

## Expected
- [ ] E1 · After the grant, toast `Roles updated`.
- [ ] E2 · Sales Exec history newest sentence is `Permissions changed · added: Delete reservation`.
- [ ] E3 · Lucía’s next authenticated request includes `bookings.delete` (network `/api/auth/me` or equivalent).
- [ ] E4 · After revert, history has a second permissions entry `Permissions changed · removed: Delete reservation` (two permission-change rows total).

## Notes
The Sprint 1 AC said “Delete bookings”. The catalogue and screen use **Delete reservation**.
