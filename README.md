# KirbyShop

A self-hosted e-commerce starter built on [Kirby CMS](https://getkirby.com) with Stripe Checkout, for developers who need to ship a cost-effective online shop for clients with moderate needs.

<img src="docs/cover.png" width="500">

**Stack:** Kirby 5 · Stripe Checkout · Vite + SCSS · PHP ≥ 8.2, Composer, Node.js (or Docker)

## Getting started

```bash
git clone https://github.com/ambientmoxie/kirby-shop.git
cd kirby-shop
composer install
npm install
cp .env-example .env   # add your Stripe keys and SMTP credentials
npm run dev
```

The site runs on `localhost:8888`. Open `localhost:8888/panel` to create your admin account. With Docker instead: `docker compose up --build`.

Keep `VITE_DEV=true` in `.env` for local work: it serves assets from Vite, enables debug mode, uses the Stripe **test** keys and allows installing the panel.

## Features

- **Cart**: session-based in a drawer. Prices and stock always come from the product pages, never the browser.
- **Stripe Checkout**, or **manual payment** when Stripe is turned off in the panel.
- **Stock reservation** during payment, so the last unit can't be sold twice.
- **Orders** as Kirby pages, managed from the panel, each with a readable number (`KS-2026-000128`).
- **Emails**: receipt to the buyer, summary to the admin.
- **Newsletter** signup, stored in the panel.

## Environment variables

| Variable | Description |
|---|---|
| `VITE_DEV` | `true` in local dev only, remove on the live server |
| `STRIPE_LIVE_PUBLIC_KEY` / `STRIPE_LIVE_SECRET_KEY` | Stripe live keys |
| `STRIPE_TEST_PUBLIC_KEY` / `STRIPE_TEST_SECRET_KEY` | Stripe test keys |
| `EMAIL_HOST` / `EMAIL_PORT` | SMTP server |
| `EMAIL_USERNAME` / `EMAIL_PASSWORD` | SMTP login. The username is also the sender and admin address |

## Deploy

Remove `VITE_DEV` from `.env`, then build and preview:

```bash
npm run preview
```

On the server, PHP needs write access to `content/`, `site/sessions/` and `site/storage/`. Keep `site/storage/` between deploys (if lost, the order counter is rebuilt from existing orders).

## License

[MIT](LICENSE). This is a work in progress and may contain bugs or security flaws: use it at your own risk.
