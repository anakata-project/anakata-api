# Task 07 · anakata-ui · Layer setup, fonts and design tokens
**Repo:** anakata-ui · **Sprint:** 0 (read `../anakata-api/docs/sprints/sprint-00/README.md` first)

## Goal
The layer loads Nuxt UI with the prototypes' exact fonts and colour tokens, in dark (default) and light themes, and every Nuxt UI colour variable points at a prototype token.

## Read first
- `.cursor/rules/anakata-core.mdc` and `.cursor/rules/ui-layer.mdc`
- The `<style>` block of the RMS prototype (path in `../anakata-api/docs/requirements/INDEX.md`): `:root`, `html[data-theme="light"]`, `body`, `h1`, `label`, `.mono`
- The same blocks in the CRM and booking-engine prototypes, to compare

## Do
1. **Dependencies.** `pnpm add @nuxt/ui tailwindcss @nuxtjs/i18n`. Run `pnpm approve-builds` if pnpm asks.
2. **`nuxt.config.ts`:**
   - `modules: ['@nuxt/ui', '@nuxtjs/i18n']`
   - `css` resolved via `createResolver(import.meta.url)`
   - `colorMode: { preference: 'dark', fallback: 'dark' }`
   - i18n with `en` only: `strategy: 'no_prefix'`, lazy file `i18n/locales/en.json`
3. **Fonts** via `@nuxt/fonts` (Google provider), with exact weights:
   - Oswald 300/400
   - Archivo 300/400/500
   - IBM Plex Mono 400/500
   - Manrope 400/500/600
4. **`app/assets/css/main.css`.** Copy the prototype variables **verbatim**. The prototypes' `:root` is **dark**; their `html[data-theme="light"]` is **light**. Nuxt UI uses `:root` for light and `.dark` for dark, so swap them:
   ```css
   @import "tailwindcss";
   @import "@nuxt/ui";

   @theme {
     --font-sans: 'Archivo', sans-serif;
     --font-display: 'Oswald', sans-serif;
     --font-mono: 'IBM Plex Mono', monospace;
     --font-manrope: 'Manrope', sans-serif;
     /* coral palette for primary: 400 #EF7365 · 500 #E85646 · 600 #D24537 — generate the remaining shades */
   }

   :root {  /* LIGHT, from html[data-theme="light"]; the later --iv38 override wins */
     --forest:#EFEDDD; --forest-950:#FAF9F0; --forest-900:#F6F4E6; --forest-700:#E7E4D0; --forest-600:#D9D4BC;
     --coral:#E85646; --coral-600:#C43D2E; --coral-400:#C03A2B;
     --ivory:#202B26; --iv62:rgba(32,43,38,.68); --iv38:rgba(32,43,38,.52);
     --sand:#8A7340; --olive:#6B6C4F; --hair:rgba(32,43,38,.16);
     --ok:#43704F; --warn:#A87428;
   }
   .dark {  /* DARK, from the prototypes' :root (default) */
     --forest:#202B26; --forest-950:#141B17; --forest-900:#1A231E; --forest-700:#2A362F; --forest-600:#37453D;
     --coral:#E85646; --coral-600:#D24537; --coral-400:#EF7365;
     --ivory:#F2F1E1; --iv62:rgba(242,241,225,.62); --iv38:rgba(242,241,225,.38);
     --sand:#E0D5B8; --olive:#585940; --hair:rgba(242,241,225,.14);
     --ok:#7FA37A; --warn:#D9A868;
   }
   :root { --ease:cubic-bezier(.16,1,.3,1); --eo:cubic-bezier(.23,1,.32,1); --eio:cubic-bezier(.77,0,.175,1); }
   ```
   Verify every value against all three prototypes, and report any difference.
5. **Map the tokens onto Nuxt UI's variables.** One block serves both modes, because the tokens already flip.

   | Nuxt UI variable | Token |
   |---|---|
   | `--ui-bg` | `--forest-950` |
   | `--ui-bg-muted` | `--forest-900` |
   | `--ui-bg-elevated` | `--forest` |
   | `--ui-bg-accented` | `--forest-700` |
   | `--ui-bg-inverted` | `--ivory` |
   | `--ui-text` | `--ivory` |
   | `--ui-text-highlighted` | `--ivory` |
   | `--ui-text-toned` | `--iv62` |
   | `--ui-text-muted` | `--iv62` |
   | `--ui-text-dimmed` | `--iv38` |
   | `--ui-text-inverted` | `--forest-950` |
   | `--ui-border` | `--hair` |
   | `--ui-border-muted` | `--hair` |
   | `--ui-border-accented` | `--forest-600` |
   | `--ui-primary` | `--coral` |
   | `--ui-radius` | `0` |

6. **Semantic colours** in `app.config.ts` → `ui.colors`:
   - primary = coral
   - success from `--ok`
   - warning from `--warn`
   - info from `--sand`
   - error from `--coral-600`

   Define custom palettes where Nuxt UI needs a palette name. **No Tailwind grey may appear anywhere**, including the neutral colour.
7. **Base styles** in `@layer base`, for the back office, taken from the RMS CSS:
   - `body`: background `--forest-950`, colour `--ivory`, Archivo 300, 13.5px, line-height 1.6
   - `h1`: Oswald 300, 21px, letter-spacing .16em, uppercase
   - Utility `.mono`: IBM Plex Mono, uppercase, .2em, 10px
   - Utility `.label`: mono 9px, .2em, uppercase, `--iv62`

## Out of scope
Component overrides (task 08) and shared components (task 09).

## Acceptance criteria
- [ ] The playground starts dark. Toggling colour mode switches every token to the light values.
- [ ] A bare `<UButton>` in the playground is coral with square corners.
- [ ] Fonts load with the exact weights listed.
- [ ] `pnpm lint` and `pnpm typecheck` pass.
- [ ] A "Task 07" section appended to `../anakata-api/docs/sprints/sprint-00/REPORT.md`, listing any token differences between the prototypes.
