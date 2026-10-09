# RML Platform

RML Energy Saving is a B2B marketplace for government-backed energy-saving leads in Spain. Sellers submit insulation, double-glazing, and heat-pump opportunities. Internal auditors check the evidence. Installers (buyers) purchase verified leads. Customer details stay hidden until payment is confirmed, and each lead can be sold only once.

**Live app:** [https://rml-platform-production-hkvml9.laravel.cloud](https://rml-platform-production-hkvml9.laravel.cloud)

**Source:** [https://github.com/ahmed-267/rml-platform](https://github.com/ahmed-267/rml-platform)

## What you can do

- Browse a public landing page (English, Spanish, and French) and register as a seller or buyer. Accounts stay pending until an admin approves them.
- Submit leads with property details, evidence, and an optional Spanish Catastro (cadastral) check.
- Audit leads, record an on-site pre-installation survey, and compare what was submitted with Catastro and survey data.
- Price a lead from its scheme, zone, and floor area (`size m² × €/m²`).
- Show leads on an admin map and estimate distance from a buyer’s coordinates.
- Let buyers purchase single leads or packages by bank transfer or card (Stripe / Mollie when configured).
- Release customer details, invoices, and seller payouts or commissions only after payment is confirmed.
- Send transactional email, and send purchased lead details over WhatsApp when the Meta Cloud API is configured.

## Stack

- Laravel 13, PHP 8.3+
- Inertia.js with React and TypeScript
- Tailwind CSS
- PostgreSQL
- Spatie Laravel Permission (roles and policies)
- Stripe and Mollie for card payments, plus manual bank transfer
- Google Maps for geocoding and the admin map
- Spanish Catastro public API (no API key)

## Run it locally

You need PHP 8.3+, Composer, Node.js 20+, and Docker.

```bash
git clone https://github.com/ahmed-267/rml-platform.git
cd rml-platform

cp .env.example .env
composer install
npm install

docker compose up -d
php artisan key:generate
php artisan migrate --seed

composer run dev
```

`docker compose up -d` starts PostgreSQL (`rml_platform` / `rml_user` / `rml_password` on port 5432) and [Mailpit](http://127.0.0.1:8025) for local email.

`composer run dev` starts the app, queue worker, log tail, and Vite together.

- App: [http://127.0.0.1:8000](http://127.0.0.1:8000)
- Vite (hot reload): [http://localhost:5173](http://localhost:5173)

If you want Mailpit to catch mail instead of writing it to the log, set this in `.env` and restart:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

## Demo accounts

After seeding, every demo user uses the password `password`.

| Role | Email |
| --- | --- |
| Super Admin | `admin@rml.test` |
| Admin staff | `staff@rml.test` |
| Internal auditor | `auditor@rml.test` |
| Seller company admin | `seller.admin@rml.test` |
| Seller staff | `seller.staff@rml.test` |
| Individual seller agent | `agent@rml.test` |
| Buyer admin | `buyer@rml.test` |
| Pending seller (no portal yet) | `pending.seller@rml.test` |

## Tests

Tests use an in-memory SQLite database and do not touch your local Postgres data.

```bash
php artisan test
```

## Optional services

The app runs without these. Add them to `.env` only when you need the feature. Never commit real keys.

| Feature | Variables |
| --- | --- |
| Stripe card checkout | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` |
| Mollie card checkout | `MOLLIE_KEY` (webhooks need a public URL) |
| Google Maps / geocoding | `GOOGLE_MAPS_SERVER_KEY`, `VITE_GOOGLE_MAPS_API_KEY` |
| WhatsApp (Meta Cloud) | `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID` |
| Catastro lookup | On by default (`CATASTRO_ENABLED=true`). No secret required. |
