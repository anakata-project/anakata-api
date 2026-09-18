# Task 08 · anakata-ui · Nuxt UI component theme
**Repo:** anakata-ui · **Sprint:** 0 (read `../anakata-api/docs/sprints/sprint-00/README.md` first)

## Goal
Nuxt UI's standard components look exactly like the RMS prototype's controls, in both themes. RMS and CRM share identical CSS, so this is the back-office theme; the engine adds its own sizes later (task 13).

## Read first
- `.cursor/rules/ui-layer.mdc`
- The RMS prototype CSS for every class in the table below, plus their `:hover`, `:focus`, `:disabled` and `.active` rules
- The CRM prototype's `.btn.o` rule
- RMS screenshots showing buttons, forms, tables, pills, modals and tabs

## Do
Configure `app.config.ts` → `ui.<component>` (slots and variants). **Take every value from the prototype CSS.** The table gives starting values already extracted; if the prototype differs, the prototype wins.

| Nuxt UI | Prototype rule | Values |
|---|---|---|
| `UButton` solid primary | `.btn` | bg `--coral`, text `--forest-950`, mono 10px, .2em, uppercase, padding 11px 20px, no border, radius 0 |
| `UButton` outline | `.btn.o` | read from the CRM prototype |
| `UInput` | `input` | bg `--forest-950`, 1px `--hair`, `--ivory`, padding 8px 10px, mono 11px, .06em, radius 0; focus state as in the prototype (no glow ring) |
| `USelect`, `USelectMenu` | `select` | bg `--forest`, 1px `--hair`, padding 8px 12px, mono 10px, .14em, uppercase |
| `UTextarea` | `textarea` | bg `--forest-950`, 1px `--hair`, padding 11px 12px, Archivo 13px |
| `UFormField` label | `label` | mono 9px, .2em, uppercase, `--iv62`, margin-bottom 6px |
| `UTable` header | `th` | mono 8.5px, .14em, uppercase, `--iv38`, padding 10px 8px, 1px `--hair` bottom |
| `UTable` cells | list tables | take from the bookings list, **not** the calendar grid |
| `UBadge` | `.pill` | mono 8.5px, .14em, uppercase, padding 3px 9px, 1px `--hair` border |
| `UModal` | `.modal`, `.mbox` | overlay `rgba(10,14,12,.75)`; content bg `--forest`, 1px `--hair`, width 640px, max 94vw, max-height 92vh, padding 34px |
| `UTabs` | booking panel tabs | Overview · Guests · Extras · Payments · Documents · History |
| `UCard` | `.panel` | 1px `--hair`, bg `--forest-900` |

Also theme `UDropdownMenu`, `UTooltip`, `UToast`, `USlideover`, `UCheckbox`, `USwitch` and `UPagination`:
- Match the prototype where it shows an equivalent.
- Where it doesn't, stay consistent with the rules above: square corners, hairline borders, mono labels, no shadows.

## Out of scope
Custom components (task 09) and the style guide page (task 10). Use a temporary playground page to check your work.

## Acceptance criteria
- [ ] Every component in the table matches the prototype side by side, in dark and light, including hover, focus and disabled states.
- [ ] No shadows, rounded corners or Tailwind grey in any themed component.
- [ ] `pnpm lint` and `pnpm typecheck` pass.
- [ ] A "Task 08" section appended to `REPORT.md`, listing every value that differed from this table and which components have no prototype equivalent.
