# Scenario catalogue

Pick by **id**, **tag**, or **priority**. P1 first. Visual is P3 (on request).

Sprint 5 `PAY-01`…`PAY-12` were written from a `reset.sh` screen on 2026-09-21. P1 grew by PAY-01, 02, 03, 05, 06, 08, 10. BKG-01 / 02 / 06 / 09 (and next-ref on BKG-03 / 04 / 05) were updated for the money seed (`ANK-2026-0021`, next ANK `0022`, Balance ≠ Total). BR-01 / BR-02 / INV-01 still have leftover Sprint 4 unverified markers.

| ID | Title | Tags | Priority | Users | File |
|---|---|---|---|---|---|
| SMK-01 | Stack is up | smoke | P1 | — | [smoke/SMK-01-stack-is-up.md](smoke/SMK-01-stack-is-up.md) |
| SMK-02 | Every demo user can sign in and out | smoke, sprint-1, auth | P1 | all four | [smoke/SMK-02-demo-users-sign-in-out.md](smoke/SMK-02-demo-users-sign-in-out.md) |
| AUTH-01 | Return to the page you asked for | sprint-1, auth | P1 | Mateo | [auth/AUTH-01-return-to-asked-page.md](auth/AUTH-01-return-to-asked-page.md) |
| AUTH-02 | Failed sign-ins look identical | sprint-1, auth | P2 | — | [auth/AUTH-02-failed-sign-ins.md](auth/AUTH-02-failed-sign-ins.md) |
| AUTH-03 | Forgot and reset a password | sprint-1, auth | P1 | Carolina | [auth/AUTH-03-forgot-reset-password.md](auth/AUTH-03-forgot-reset-password.md) |
| AUTH-04 | Accept an invitation | sprint-1, auth | P1 | Carolina + new | [auth/AUTH-04-accept-invitation.md](auth/AUTH-04-accept-invitation.md) |
| AUTH-05 | Accept while signed in as someone else | sprint-1, auth | P2 | Carolina + new | [auth/AUTH-05-accept-while-signed-in.md](auth/AUTH-05-accept-while-signed-in.md) |
| AUTH-06 | A disabled user is signed out on their next action | sprint-1, auth | P2 | Carolina, Lucía | [auth/AUTH-06-disabled-user-signed-out.md](auth/AUTH-06-disabled-user-signed-out.md) |
| AUTH-07 | Redirects stay inside the panel | sprint-1, auth | P2 | Mateo | [auth/AUTH-07-redirects-stay-inside.md](auth/AUTH-07-redirects-stay-inside.md) |
| AUTH-08 | Section access | sprint-1, auth | P1 | CFO, Mateo, Lucía | [auth/AUTH-08-section-access.md](auth/AUTH-08-section-access.md) |
| USR-01 | Invite, edit, disable, enable, resend | sprint-1, users-roles | P2 | Carolina | [users-roles/USR-01-invite-edit-disable-enable-resend.md](users-roles/USR-01-invite-edit-disable-enable-resend.md) |
| USR-02 | Your own row | sprint-1, users-roles | P2 | Carolina | [users-roles/USR-02-own-row.md](users-roles/USR-02-own-row.md) |
| USR-03 | No privilege escalation from a limited role | sprint-1, users-roles | P2 | Carolina + limited | [users-roles/USR-03-no-privilege-escalation.md](users-roles/USR-03-no-privilege-escalation.md) |
| ROLE-01 | Grant, use, revert a permission | sprint-1, users-roles | P1 | Carolina, Lucía | [users-roles/ROLE-01-grant-use-revert.md](users-roles/ROLE-01-grant-use-revert.md) |
| ROLE-02 | Own only vs any | sprint-1, users-roles | P2 | Carolina | [users-roles/ROLE-02-own-only-vs-any.md](users-roles/ROLE-02-own-only-vs-any.md) |
| ROLE-03 | Custom role lifecycle | sprint-1, users-roles | P2 | Carolina | [users-roles/ROLE-03-custom-role-lifecycle.md](users-roles/ROLE-03-custom-role-lifecycle.md) |
| ROLE-04 | Unsaved matrix changes | sprint-1, users-roles | P2 | Carolina | [users-roles/ROLE-04-unsaved-matrix.md](users-roles/ROLE-04-unsaved-matrix.md) |
| ROLE-05 | Read-only matrix | sprint-1, users-roles | P2 | Carolina + limited | [users-roles/ROLE-05-read-only-matrix.md](users-roles/ROLE-05-read-only-matrix.md) |
| RATE-01 | Rates read-only | sprint-2, config | P2 | Lucía | [config/RATE-01-rates-read-only.md](config/RATE-01-rates-read-only.md) |
| RATE-02 | Year helpers | sprint-2, config | P2 | Carolina | [config/RATE-02-year-helpers.md](config/RATE-02-year-helpers.md) |
| RATE-03 | Price check matches the reference prices | sprint-2, config | P1 | Carolina | [config/RATE-03-price-check-reference.md](config/RATE-03-price-check-reference.md) |
| RATE-04 | Invalid, then valid again | sprint-2, config | P2 | Carolina | [config/RATE-04-invalid-then-valid.md](config/RATE-04-invalid-then-valid.md) |
| RATE-05 | Publish and history | sprint-2, config | P1 | Carolina | [config/RATE-05-publish-and-history.md](config/RATE-05-publish-and-history.md) |
| RATE-06 | Two editors, one wins | sprint-2, config | P1 | Carolina ×2 | [config/RATE-06-two-editors.md](config/RATE-06-two-editors.md) |
| ENG-01 | Manager publishes copy without a reference | sprint-2, config | P1 | Mateo | [config/ENG-01-manager-copy-no-reference.md](config/ENG-01-manager-copy-no-reference.md) |
| ENG-02 | Manager can't touch rules | sprint-2, config | P1 | Mateo | [config/ENG-02-manager-rules-locked.md](config/ENG-02-manager-rules-locked.md) |
| ENG-03 | Rule change needs a reference | sprint-2, config | P2 | Carolina | [config/ENG-03-rule-change-needs-reference.md](config/ENG-03-rule-change-needs-reference.md) |
| ENG-04 | Typing group contexts | sprint-2, config | P2 | Carolina | [config/ENG-04-typing-group-contexts.md](config/ENG-04-typing-group-contexts.md) |
| ENG-05 | Engine settings read-only | sprint-2, config | P2 | Lucía | [config/ENG-05-engine-read-only.md](config/ENG-05-engine-read-only.md) |
| BR-01 | Fresh-seed registry | sprint-2, config | P1 | Carolina | [config/BR-01-fresh-seed-registry.md](config/BR-01-fresh-seed-registry.md) |
| BR-02 | A differing value, reset, publish | sprint-2, config | P1 | Carolina | [config/BR-02-differ-reset-publish.md](config/BR-02-differ-reset-publish.md) |
| BR-03 | Cancellation bands | sprint-2, config | P2 | Carolina | [config/BR-03-cancellation-bands.md](config/BR-03-cancellation-bands.md) |
| BR-04 | A change on another page shows here | sprint-2, config | P2 | Carolina | [config/BR-04-change-on-another-page.md](config/BR-04-change-on-another-page.md) |
| BR-05 | "No cap" never becomes zero | sprint-2, config | P2 | Carolina | [config/BR-05-no-cap-never-zero.md](config/BR-05-no-cap-never-zero.md) |
| BR-06 | Who can see Business Rules | sprint-2, config | P2 | Mateo, Lucía | [config/BR-06-who-can-see-business-rules.md](config/BR-06-who-can-see-business-rules.md) |
| VIS-01 | Pages against the prototype | visual, sprint-2 | P3 | Carolina | [visual/VIS-01-pages-against-prototype.md](visual/VIS-01-pages-against-prototype.md) |
| VIS-02 | Theme toggle everywhere | visual | P3 | Carolina | [visual/VIS-02-theme-toggle.md](visual/VIS-02-theme-toggle.md) |
| INV-01 | Seeded inventory in the Calendar | sprint-3, inventory | P1 | Carolina | [inventory/INV-01-seeded-calendar.md](inventory/INV-01-seeded-calendar.md) |
| INV-02 | Write and publish an itinerary | sprint-3, inventory | P1 | Carolina | [inventory/INV-02-write-publish-itinerary.md](inventory/INV-02-write-publish-itinerary.md) |
| INV-03 | Itinerary photo | sprint-3, inventory | P2 | Carolina | [inventory/INV-03-itinerary-photo.md](inventory/INV-03-itinerary-photo.md) |
| INV-04 | Itinerary delete guard | sprint-3, inventory | P2 | Carolina | [inventory/INV-04-itinerary-delete-guard.md](inventory/INV-04-itinerary-delete-guard.md) |
| INV-05 | Departure date rules | sprint-3, inventory | P1 | Carolina | [inventory/INV-05-departure-date-rules.md](inventory/INV-05-departure-date-rules.md) |
| INV-06 | Generate a season | sprint-3, inventory | P1 | Carolina | [inventory/INV-06-generate-season.md](inventory/INV-06-generate-season.md) |
| INV-07 | Status and engine label | sprint-3, inventory | P2 | Carolina | [inventory/INV-07-status-engine-label.md](inventory/INV-07-status-engine-label.md) |
| INV-08 | Block, see, release | sprint-3, inventory | P1 | Mateo | [inventory/INV-08-block-see-release.md](inventory/INV-08-block-see-release.md) |
| INV-09 | Block conflict | sprint-3, inventory | P1 | Mateo | [inventory/INV-09-block-conflict.md](inventory/INV-09-block-conflict.md) |
| INV-10 | Departure locks with a block | sprint-3, inventory | P2 | Carolina | [inventory/INV-10-departure-locks.md](inventory/INV-10-departure-locks.md) |
| INV-11 | Rates year guard | sprint-3, inventory | P2 | Carolina | [inventory/INV-11-rates-year-guard.md](inventory/INV-11-rates-year-guard.md) |
| INV-12 | Read-only inventory for Lucía | sprint-3, inventory | P2 | Lucía | [inventory/INV-12-lucia-read-only.md](inventory/INV-12-lucia-read-only.md) |
| BKG-01 | Seeded bookings and segments | sprint-4, bookings | P1 | Carolina | [bookings/BKG-01-seeded-bookings-segments.md](bookings/BKG-01-seeded-bookings-segments.md) |
| BKG-02 | Create a one-cabin reservation | sprint-4, bookings | P1 | Carolina | [bookings/BKG-02-create-one-cabin.md](bookings/BKG-02-create-one-cabin.md) |
| BKG-03 | Create a three-cabin group | sprint-4, bookings | P1 | Carolina | [bookings/BKG-03-create-three-cabin-group.md](bookings/BKG-03-create-three-cabin-group.md) |
| BKG-04 | Festive charter | sprint-4, bookings | P2 | Carolina | [bookings/BKG-04-festive-charter.md](bookings/BKG-04-festive-charter.md) |
| BKG-05 | No double booking | sprint-4, bookings | P1 | Carolina ×2 | [bookings/BKG-05-no-double-booking.md](bookings/BKG-05-no-double-booking.md) |
| BKG-06 | Legal transitions and cancellation | sprint-4, bookings | P1 | Carolina | [bookings/BKG-06-transitions-cancel.md](bookings/BKG-06-transitions-cancel.md) |
| BKG-07 | Date change reprices | sprint-4, bookings | P2 | Carolina | [bookings/BKG-07-date-change-reprice.md](bookings/BKG-07-date-change-reprice.md) |
| BKG-08 | Delete is admin-only and audited | sprint-4, bookings | P2 | Mateo, Carolina | [bookings/BKG-08-delete-admin-audit.md](bookings/BKG-08-delete-admin-audit.md) |
| BKG-09 | Request queue | sprint-4, bookings | P1 | Carolina | [bookings/BKG-09-request-queue.md](bookings/BKG-09-request-queue.md) |
| BKG-10 | Expired request hold | sprint-4, bookings | P2 | Carolina | [bookings/BKG-10-expired-request-hold.md](bookings/BKG-10-expired-request-hold.md) |
| BKG-11 | Waitlist | sprint-4, bookings | P2 | Carolina | [bookings/BKG-11-waitlist.md](bookings/BKG-11-waitlist.md) |
| BKG-12 | Own-records | sprint-4, bookings | P2 | Lucía | [bookings/BKG-12-own-records.md](bookings/BKG-12-own-records.md) |
| PAY-01 | Seeded ledger on a CONFIRMED booking | sprint-5, payments | P1 | Carolina | [payments/PAY-01-seeded-ledger.md](payments/PAY-01-seeded-ledger.md) |
| PAY-02 | Record the balance → FULLY PAID | sprint-5, payments | P1 | Carolina | [payments/PAY-02-record-balance.md](payments/PAY-02-record-balance.md) |
| PAY-03 | Awaiting wire, then mark received | sprint-5, payments | P1 | Carolina then CFO | [payments/PAY-03-mark-wire.md](payments/PAY-03-mark-wire.md) |
| PAY-04 | Create, copy, cancel a deposit link | sprint-5, payments | P2 | Carolina | [payments/PAY-04-payment-link-lifecycle.md](payments/PAY-04-payment-link-lifecycle.md) |
| PAY-05 | Stripe test-mode or fake-webhook replay | sprint-5, payments | P1 | Carolina | [payments/PAY-05-stripe-or-replay.md](payments/PAY-05-stripe-or-replay.md) |
| PAY-06 | Payments & Revenue KPIs and pending | sprint-5, payments | P1 | Carolina | [payments/PAY-06-payments-revenue.md](payments/PAY-06-payments-revenue.md) |
| PAY-07 | Apply the unmatched gateway charge | sprint-5, payments | P2 | Carolina | [payments/PAY-07-reconciliation.md](payments/PAY-07-reconciliation.md) |
| PAY-08 | OVERDUE flag and OPS-007 extension | sprint-5, payments | P1 | Carolina | [payments/PAY-08-overdue-extension.md](payments/PAY-08-overdue-extension.md) |
| PAY-09 | OPS-007 cancel per policy | sprint-5, payments | P2 | Carolina | [payments/PAY-09-overdue-cancel.md](payments/PAY-09-overdue-cancel.md) |
| PAY-10 | Refund Approvals approve and execute | sprint-5, payments | P1 | Carolina then CFO | [payments/PAY-10-refund-approvals.md](payments/PAY-10-refund-approvals.md) |
| PAY-11 | Trade 15 % hold then commission approval | sprint-5, payments | P2 | Carolina | [payments/PAY-11-on-hold-agency.md](payments/PAY-11-on-hold-agency.md) |
| PAY-12 | B2B breached registration and partners | sprint-5, payments | P2 | Carolina | [payments/PAY-12-b2b-agencies.md](payments/PAY-12-b2b-agencies.md) |
