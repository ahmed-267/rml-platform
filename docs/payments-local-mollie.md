# Local Mollie / card payment testing

RML supports card checkout via Mollie and manual bank transfer. Customer details are never released until payment is confirmed (webhook or admin bank-transfer confirmation). The return URL alone never marks a payment as paid.

## Environment variables

```env
PAYMENT_DEFAULT_PROVIDER=manual_bank_transfer
PAYMENT_CARD_PROVIDER=mollie
PAYMENT_CURRENCY=EUR

MOLLIE_KEY=
MOLLIE_WEBHOOK_SECRET=
MOLLIE_WEBHOOK_URL=

APP_URL=https://your-public-url.example
```

- `MOLLIE_KEY` — Mollie API key (`test_...` for sandbox, `live_...` for production). When empty or a placeholder, card checkout is disabled and buyers are guided to manual bank transfer.
- `MOLLIE_WEBHOOK_URL` — optional override; defaults to `{APP_URL}/webhooks/mollie`.
- `MOLLIE_WEBHOOK_SECRET` — reserved for webhook verification hardening if enabled later.
- `APP_URL` — must be publicly reachable for Mollie webhooks (not `http://127.0.0.1`).

## Local card payment checklist

1. Create a Mollie test API key in the Mollie dashboard.
2. Add it to `.env` as `MOLLIE_KEY=test_...`.
3. Expose the app with ngrok (or similar) and set `APP_URL` to that public HTTPS URL.
4. Optionally set `MOLLIE_WEBHOOK_URL={APP_URL}/webhooks/mollie`.
5. Run `php artisan config:clear`.
6. As a buyer, purchase a lead with **Card**.
7. Complete payment in Mollie sandbox/test mode.
8. Confirm the webhook updates payment status to paid and lead details are released.
9. Confirm a failed/cancelled payment does **not** release customer details.

## Without Mollie configured

- Purchase pages show a single warning: card payments are not configured; use manual bank transfer.
- Super Admin / Admin Staff may also see: `MOLLIE_KEY is missing from the environment configuration.`
- Manual bank transfer still works: bank instructions, payment reference, amount, pending status, admin confirmation.
- Missing credentials must not crash the app or release lead details.
