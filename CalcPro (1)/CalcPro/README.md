# CalcPro

A pay-to-calculate SaaS demo. Calculations cost **$300 each** (credit) or **$99/month** (Monthly Pro, unlimited).

## Technologies
PHP 8+ (no Composer packages, no database server), HTML, CSS, vanilla JavaScript. Data is stored in `data/db.json` (outside the web root).
Passwords use `password_hash`; auth uses PHP sessions (HttpOnly cookie). Calculations are evaluated server-side by a safe parser (no `eval`).

## Run locally
```bash
cp .env.example .env     # optional
php -S localhost:8000 -t public
```
Open http://localhost:8000, sign up, buy a credit or subscribe (the demo checkout is free), then calculate.
On Apache/Nginx, point the document root to `public/` and make sure `data/` is writable by PHP.

## Environment variables
| Name | Purpose |
|---|---|
| `PAYMENT_PROVIDER` | `mock` (default, no keys) or `stripe` |
| `STRIPE_SECRET_KEY` | Stripe secret key (backend only) |
| `STRIPE_WEBHOOK_SECRET` | Verifies Stripe webhooks |

## Structure
- `public/index.html`, `public/css/style.css`, `public/js/app.js` – frontend (SPA with hash routes)
- `public/api.php` – API: auth, calculator, credits, subscription, history, settings
- `src/payments.php` – **all payment logic**; the frontend never touches cards or keys
- `src/Calc.php` – safe math parser
- `data/` – JSON storage (created automatically, never served)

## Connecting Stripe
1. Set `STRIPE_SECRET_KEY` in `.env` and `PAYMENT_PROVIDER=stripe`.
2. In `src/payments.php`, create a Stripe Checkout Session with cURL (`mode=payment` for $300, `mode=subscription` with a $99 recurring price for Pro) and return its URL; make the frontend redirect to it.
3. Add a `webhook.php` that verifies the `Stripe-Signature` header with `STRIPE_WEBHOOK_SECRET` and then grants the credit / extends `sub.end`.
4. Never grant access from the browser redirect alone; use the webhook.

Production notes: replace JSON storage with MySQL/PostgreSQL, serve over HTTPS and add the `Secure` cookie flag.
