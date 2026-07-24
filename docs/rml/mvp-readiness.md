# RML MVP readiness

Phase status: **Phases 1–9 complete** (MVP polish / QA). Not production-deployed.

## Demo logins

Password for all: `password`

| Role | Email |
|------|-------|
| Super Admin | `admin@rml.test` |
| Admin Staff | `staff@rml.test` |
| Internal Auditor | `auditor@rml.test` |
| Seller Company Admin | `seller.admin@rml.test` |
| Seller Staff | `seller.staff@rml.test` |
| Individual Seller Agent | `agent@rml.test` |
| Buyer Admin | `buyer@rml.test` |
| Pending Seller | `pending.seller@rml.test` |

Additional expanded-demo users are created by `ExpandedDemoDataSeeder` (e.g. `solis.buyer@rml.test`, `ecocasa.admin@rml.test`). Same password.

## Main test flows

1. **Landing** — `/` EN/ES/FR switcher, CTAs to register/login/homeowner eligibility.
2. **Seller** — submit lead → upload evidence → track audit → view payouts/commissions.
3. **Auditor** — assigned audits → recommend accept/reject/needs more info.
4. **Admin** — approvals → lead audit/pricing → list → confirm payments → reports.
5. **Buyer** — marketplace → purchase (bank transfer) → wait for confirmation → details unlock → download invoice/receipt.
6. **Staff invite (seller)** — seller admin invites staff → accept token link → login as staff.

## Payments

- **Manual bank transfer** — default local path. Buyer sees IBAN/reference; details stay locked until Super Admin marks paid.
- **Mollie card** — configure `MOLLIE_KEY` (+ public `APP_URL` for webhooks). See `docs/payments-local-mollie.md`.
- Missing Mollie key shows a single clear warning; does not crash; does not release details.

## Local email (Mailpit)

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
```

Or use `MAIL_MAILER=log`. Staff invitations and payment emails are queued/sent through Laravel mail.

## WhatsApp

Provider-ready via Meta Cloud API env vars (`WHATSAPP_*`). When not configured, UI shows a clean not-configured state. Send is blocked until purchase is paid and details are released.

## Seed / verify

```bash
php artisan migrate:fresh --seed
php artisan test
npm run build
```

## Known MVP limitations

- No buyer-staff invitation role/flow yet (seller staff invite only).
- No GoCardless / Redsys / PAYCOMET / OCR / AI scoring / subscriptions / homeowner portal.
- Mollie webhooks need a public URL (ngrok) for local card tests.
- Some legacy Breeze profile pages may still contain residual English until fully replaced by portal profiles.
- WhatsApp and Mollie require real credentials for live end-to-end external calls.

## After MVP (not Phase 9)

- Production hosting, SSL, backups, monitoring
- Real bank details and live Mollie keys
- Brand photography for landing
- Buyer staff invites (if required)
- Additional payment providers as needed
- Hardening of webhook signature verification if Mollie signing secrets are enabled

## Stakeholder testing readiness

The MVP is suitable for internal stakeholder and test-user walkthroughs with demo seed data, manual bank transfer, EN/ES/FR UI, and role-separated portals.
