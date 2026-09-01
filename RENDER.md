# Deploy NexTradeAI app on Render

## 1. GitHub

Repo: **https://github.com/Mabena119/nextrade-app** (main NexTrade Expo web + API)

Chart warmup API (separate): **https://github.com/Mabena119/nextradeai**

## 2. Render Blueprint

1. [Render Dashboard](https://dashboard.render.com) → **New** → **Blueprint**
2. Connect `Mabena119/nextrade-app`
3. Set environment variables:

```env
GOOGLE_AI_API_KEY=<your-gemini-key>
GMAIL_USER=auraaivps@gmail.com
GMAIL_PASS=<app-password>
AURAAI_EMAIL_RELAY_SECRET=<relay-secret>
API_UPSTREAM_URL=https://auraai-vps.com
EXPO_PUBLIC_CHART_WARMUP_ENABLED=false
```

4. Deploy → URL will be `https://nextrade-app.onrender.com`

## 3. Chart warmup

**Disabled by default** for NexTrade (`EXPO_PUBLIC_CHART_WARMUP_ENABLED=false`).

The bot still runs **database signal copy-trading**. It will not open the idle AI chart scan / warmup flow.

To re-enable later: set `EXPO_PUBLIC_CHART_WARMUP_ENABLED=true` and redeploy.

## 4. What runs where

| Service | Host |
|---------|------|
| Web app + API | Render `nextrade-app` |
| Marketing site + admin | Lightsail `nextradeai.io` |
| Auth/DB proxy (read-only) | `auraai-vps.com` via `API_UPSTREAM_URL` |
| Chart analysis API (optional) | Render `nextradeai` repo |
