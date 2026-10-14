# COPILOT-01 · The copilot opens from Deals and keeps a conversation
- **Tags:** sprint-16, copilot, panel
- **Priority:** P2
- **Batch:** B21
- **Users:** Carolina
- **Start:** reset

## Why
Staff with `copilot.use` can open the copilot from any panel page, start a conversation, and send a message. A write is not applied until they approve it. This does not require a live model reply.

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/deals?view=board`.
2. Click the bot button in the top bar (or press Ctrl+Shift+K).
3. Click **New conversation**.
4. Send `Find a contact`.
5. Wait until the turn either answers or shows the failure sentence.

## Expected
- [ ] E1 · The bot button is in the top bar. The sheet opens from the bottom and shows **Copilot**.
- [ ] E2 · After **New conversation**, the list shows that conversation and the composer is usable.
- [ ] E3 · The sent text stays in the thread. Either an assistant reply arrives, or the failure sentence is visible. The sheet does not sit on **Working** forever.
- [ ] E4 · No booking, payment, or deal was created by step 4.

## Notes
A live reply needs `ANTHROPIC_API_KEY` and Reverb on port 8001. Without the key, E3 is the failure sentence. Do not approve a write in this scenario.
