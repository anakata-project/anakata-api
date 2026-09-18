# Task 10 · anakata-ui · Style guide and release v0.1.0
**Repo:** anakata-ui · **Sprint:** 0 (read `../anakata-api/docs/sprints/sprint-00/README.md` first)

## Goal
A style-guide page proves visual parity with the prototypes, and `v0.1.0` is tagged for the apps.

## Read first
- `.cursor/rules/ui-layer.mdc`
- The "Task 07"–"Task 09" sections of `REPORT.md`

## Do
1. **Style guide.** A page in `.playground` (route `/`) that shows:
   - every colour token as a swatch with its name and value
   - the type scale
   - every themed Nuxt UI component from task 08, in each state (default, hover, focus, disabled, error)
   - every `Ank*` component and variant
   - a theme toggle at the top

   Where a prototype screenshot exists for an element (buttons, inputs, table, pills, modal), show it beside the component so parity is visible. Copy the images into `.playground/public/reference/`.

   Remove the temporary pages from tasks 07–09.
2. **Tooling:**
   - `@nuxt/eslint`, `vue-tsc`, Vitest
   - Scripts: `dev` (playground on port **3010**, so it never collides with the engine on 3000), `lint`, `typecheck`, `test`, `build` (builds the playground)
3. **README.md:**
   - What the layer provides
   - How apps consume it: local path `../anakata-ui` only. Remote consumption will be decided with the git host.
   - The rule that `@nuxt/ui` and `tailwindcss` versions must match the apps
   - How to release
4. **CHANGELOG.md** with the `v0.1.0` entry. Commit, then tag `v0.1.0` locally and push it to whatever remote exists.

## Acceptance criteria
- [ ] The style guide shows every token and component. It is dark by default, and light works via the toggle.
- [ ] Side by side with the reference screenshots, the components are indistinguishable.
- [ ] Tag `v0.1.0` exists locally (and is pushed to the remote if one exists).
- [ ] A "Task 10" section appended to `REPORT.md`.
