# Demo accounts

Seeded only when `APP_ENV` is `local` or `testing` ([`DemoUsersSeeder`](../../../database/seeders/DemoUsersSeeder.php)). Password for every user: `password`.

After sign-in the header who-menu shows `{NAME} — {ROLE}` **uppercased** (em dash). Landing with no `redirect` is `/rms/reservations/calendar` for anyone with `panel.rms`.

| Name on screen | Email | Role name | Header |
|---|---|---|---|
| Carolina M. | carolina@anakata.test | Admin | `CAROLINA M. — ADMIN` |
| Mateo R. | mateo@anakata.test | Manager | `MATEO R. — MANAGER` |
| Lucía B. | lucia@anakata.test | Sales Exec | `LUCÍA B. — SALES EXEC` |
| CFO (external) | cfo@anakata.test | External finance | `CFO (EXTERNAL) — EXTERNAL FINANCE` |

## What each should and should not see

### Carolina (Admin)
- RMS and CRM. Section switch **RMS** / **CRM** is visible.
- Sidebar includes **Permissions** and **Business Rules**.
- **＋ New Reservation** is visible on RMS.
- Rates, Engine Settings and Business Rules are editable (publish with an approval reference where required).

### Mateo (Manager)
- RMS and CRM. Section switch visible. **＋ New Reservation** visible.
- **No** sidebar items for **Business Rules** or **Permissions**. Direct URLs redirect to Calendar with toast `You don't have permission to do that.`
- Engine Settings: **copy** panels editable (`Booking notes & messages`, `Confirmation page — what happens next`, charter copy). **Rules** locked: guests, calendar, fee amounts, charter **Response SLA (hours)**.
- Rates: read-only (no `rates.manage`).

### Lucía (Sales Exec)
- RMS and CRM. Section switch visible. **＋ New Reservation** visible.
- **No** Business Rules or Permissions in the sidebar. Direct URLs → Calendar + the same forbidden toast.
- Rates: **VIEW ONLY — ADMIN / DIRECTOR EDITS RATES**. Inputs disabled, no helper row, no approval field.
- Engine Settings: **VIEW ONLY — SALES EXEC**. Everything disabled.

### CFO (external)
- **RMS only.** No section switch. No **＋ New Reservation**.
- `/crm/...` redirects to `/rms/reservations/calendar`.
- No Business Rules or Permissions.
