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

- **Cart**: Session-based cart displayed in a drawer.
- **Stripe Checkout**, or **manual payment** when Stripe is turned off in the panel.
- **Stock reservation** during payment, so the last unit can't be sold twice.
- **Orders** as Kirby pages, managed from the panel, each with a readable number (`KS-2026-000128`).
- **Emails**: receipt to the buyer, summary to the admin.
- **Newsletter** signup, stored in the panel.

## Checkout process

### 1. Adding products to the cart

When a customer adds a product to their cart, the browser sends only its page UUID and a quantity. The session stores the UUID and quantity, limited to the available stock. Product information such as title, price, and stock is always retrieved from the Kirby product page when displaying the cart or processing checkout, never from the browser.

### 2. Placing an order

Once on the checkout page, the customer fills in the form with their personal and shipping information before placing the order.

Two scenarios are possible, depending on whether Stripe is enabled in the Kirby Panel.

**Case 1: Stripe is disabled**

The order is created and confirmed right away: stock decremented, number assigned, emails sent. The vendor collects payment.

**Case 2: Stripe is enabled**

Before redirecting to Stripe, a pending order is created, reserving the stock for 40 minutes. The Stripe page expires after 31 minutes, so it always closes before the reservation does.

- **Payment completed:** After payment, Stripe redirects the customer to the success page, where the payment is verified with Stripe and the order is confirmed. Stock is decremented, an order number is assigned, and emails are sent. If the customer closes the tab before reaching this page, the order is confirmed the next time they attempt to checkout on the site.
- **Payment abandoned:** the reservation stops counting after 40 minutes. The order is deleted at the next checkout on the site once its Stripe session has expired.
- **Customer returns to checkout** (same browser): the previous order is released before a new one is created. If it was paid in the meantime, the customer is sent to the success page instead, so they aren't charged twice.

This prevents abandoned checkouts from permanently blocking stock and avoids creating duplicate paid orders.

## Environment variables

| Variable                                            | Description                                                   |
| --------------------------------------------------- | ------------------------------------------------------------- |
| `VITE_DEV`                                          | `true` in local dev only, remove on the live server           |
| `STRIPE_LIVE_PUBLIC_KEY` / `STRIPE_LIVE_SECRET_KEY` | Stripe live keys                                              |
| `STRIPE_TEST_PUBLIC_KEY` / `STRIPE_TEST_SECRET_KEY` | Stripe test keys                                              |
| `EMAIL_HOST` / `EMAIL_PORT`                         | SMTP server                                                   |
| `EMAIL_USERNAME` / `EMAIL_PASSWORD`                 | SMTP login. The username is also the sender and admin address |

## Deploy

Remove `VITE_DEV` from `.env`, then build and preview:

```bash
npm run preview
```

On the server, PHP needs write access to `content/`, `site/sessions/` and `site/storage/`. Keep `site/storage/` between deploys (if lost, the order counter is rebuilt from existing orders).

## License

[MIT](LICENSE). This is a work in progress and may contain bugs or security flaws: use it at your own risk.
