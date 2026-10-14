# Sprint 16 · Report

## Task 01 · Staff copilot

The panel has a copilot button (Lucide bot) for anyone with `copilot.use`. Admin, Manager, and Sales Exec have it. A conversation is private to the staff member who opened it. A message is queued on the `ai` Horizon queue and streamed back through Reverb. The first message becomes the title. Writes are the six CRM tools, and each one waits for an approval card. Reads go through one tool that uses the same policies as the panel.

### Files

- `anakata-api`: `laravel/ai` and `laravel/reverb`, copilot migrations, `Permission::CopilotUse`, `AnakataCopilotAgent`, read and write tools, dispatcher, Reverb channel, `/api/copilot` routes, Horizon `supervisor-ai`, Reverb in supervisord.
- `anakata-panel`: Echo client, copilot sheet, header button, English and Spanish strings.
- `anakata-ui`: `copilot.use` on the generated permission union.

### Deviations

- Decision K4 still stands for the public booking engine. The staff copilot is the exception: the operator asked for the same queued-job plus Reverb transport as the reference copilot.
- Reverb listens on `8080` inside the API container. The panel connects to published port `8001`.
- `guzzlehttp/guzzle` moved to 7.15.5 so Reverb 1.12 could install. Laravel framework moved to 13.36 with the same composer update.
- The Anthropic key is empty until it is set in `.env`. A turn then fails in the sheet instead of calling a model.

### Open

- LEG-002: name, email, and phone are sent to Anthropic because the copilot has to work with contacts. Passport, medical notes, date of birth, and nationality are not.
- Usage cost, AI titles, booking tools, and voice are later.

### Notes for later

- Restart Horizon after a deploy so the `ai` queue worker loads. Reverb must be running or the sheet will accept a message and then show the failure.
- Soft-deleted conversations are hidden by the controller. The SDK store does not apply Eloquent soft deletes if a caller already has the id.
