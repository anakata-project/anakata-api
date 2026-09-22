# PRIV-02 · An objection withdraws marketing, profiling and remarketing
- **Tags:** sprint-10, crm
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset
- **Needs:** PRIV-01’s contact, or any contact with marketing opted in

## Why
Completing an objection is the withdrawal. Transactional continues. The register count drops.

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/crm/system/consent`. Read the Marketing contacts count.
2. **New request**: type **Objection**, the PRIV-01 contact, channel **Email**.
3. Open it. Enter how it was verified. **Complete** with an outcome.
4. Reload the register. Open the contact and open **History**.

## Expected
- [ ] E1 · Marketing, profiling and remarketing are **NOT OPTED IN**. Each history row for that withdrawal has capture point `SUBJECT_REQUEST`.
- [ ] E2 · The Marketing contacts count is one lower than step 1.
- [ ] E3 · Transactional stays **ALWAYS ON**.

## Notes
Do not erase the contact here.
