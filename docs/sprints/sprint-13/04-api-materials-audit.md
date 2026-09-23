# Task 04 · anakata-api · Sales materials, suspension and the agent audit
**Repo:** anakata-api · **Sprint:** 13 · **Needs:** task 03.

## Goal
The files agents need, under the team's control, with every download on the record (P5, P6).

## Read first
- `docs/requirements/08-dev-decisions.md`: **P5, P6**, P8, and B4, I7, J2, N4 (a private disk with purge-only triggers)
- The manifests disk and its triggers (Sprint 11 task 03), the reports disk (Sprint 12 task 02), `documentFetch` on the panel side

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.** `sales_materials`: `title`, `kind` (FACT_SHEET, BRAND_DECK, PHOTOGRAPHY, ITINERARY_PDF, VIDEO, OTHER), `agency_id` (null means every agency), `version`, `file_path`, `mime`, `bytes`, `uploaded_by`, `published` (bool), `purged_at`, audit columns. A private `materials` disk. Triggers refuse delete; update only for `published`, `purged_at` and the path.
2. **Upload (RMS).** `POST /api/rms/sales-materials` (multipart; `agencies.manage`): title, kind, optional agency, file. Accept PDF, PNG, JPG, MP4 and ZIP up to a stated size; reject anything else by content type, not by extension alone. A new upload with the same title and agency is a new version; the old one stays and is unpublished. `PATCH …/{material}` toggles `published`; `GET /api/rms/sales-materials` lists them with their versions.
3. **Portal.** `GET /api/portal/sales-materials` lists the published materials for this agency plus the shared ones (title, kind, size, version, updated), and `GET …/{material}/file` streams it. A material belonging to another agency is 404 (P3).
4. **Audit (P5).** Every portal download writes `portal.material_downloaded` on the agency (user, material, version). Every RMS upload and publish change writes its own history row.
5. **Retention.** Materials are business documents, not personal data: they are not purged by the retention job. Unpublishing hides them; a replaced version stays for the audit trail. Say this in the REPORT.
6. **The agent activity view.** `GET /api/rms/agencies/{agency}/portal-activity` (`agencies.manage`): the agency's portal history — sign-ins, failed sign-ins, requests created, downloads — newest first, paginated, each naming the agency user. This is the screen task 09 renders and the audit doc 06 asks for.

## Don't
- Don't serve a material from a public URL or a signed CDN link.
- Don't accept a file type on extension alone.
- Don't purge materials with guest data retention.

## Checks
- `composer check`.
- Upload, version, publish and unpublish; a rejected type and an oversized file; the portal list for a shared and an agency-specific material; another agency's material is 404.
- A download writes one history row; the activity view shows it.

## Report
Append **Task 04**: the schema and disk, upload rules and versions, the portal list and download, the audit events, the activity endpoint, and the retention position. Git commands listed, not run.
