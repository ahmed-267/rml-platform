# RML UX Consistency Audit

Focused consistency/confusion audit of the RML Platform (Laravel + Inertia React).  
Scope: navigation, roles, statuses, privacy/pricing labels, forms, tables, modals, payments, landing/public site, language, and mobile.

**Public brand target:** RML Energy Saving.

---

## Fixed in this pass

- **Status wording standardised** — Seller/Auditor KPIs, audited subtitles, admin report legends/chart copy, and mail subjects use the simplified visible set (Draft, Pending Review, Needs Information, Listed, Sold, Rejected, Cancelled). Backend enums unchanged; UI uses `LeadStatusPresentation` + `lead_statuses`.
- **Breeze/auth/profile leftover copy cleaned** — Confirm password, verify email, and `/profile` rebuilt on RML `AuthLayout` / `AppLayout` with EN/ES/FR `profile.*` keys and app Modal for delete account. Back to home remains on auth pages.
- **Brand leftovers corrected** — Visible “RML Energy Exchange” replaced with **RML Energy Saving** in landing, auth, PDFs, mail, WhatsApp subject, bank transfer default name, Stripe line-item copy, and `.env.example`.
- **Seller wizard step validation corrected** — Continue validates the current step only; Save Draft unchanged; final Submit still full validation; server errors jump to the first failing step with a readable alert.
- **Mobile filter drawer consistency improved** — `FilterBar` hides inline filters on mobile when drawer mode is on; major Seller/Buyer/Admin/Auditor listing pages (including Buyer Buy Leads) use `MobileFilterDrawer` with Apply/Reset.
- **Role labels standardised** — Visible roles aligned to Super Admin, Admin Staff, Internal Auditor, Seller Admin, Seller Staff, Seller Agent, Buyer (`roles.*`, `seller_types.*`, `UserRole::label()` via i18n, `RolePresentation` helper).
- **Payment method/provider labels clarified** — Method = Card / Manual bank transfer; Provider = Stripe / Mollie / Manual (`payment_providers`, admin payments table, buyer success page). Stripe Checkout product names use translated RML Energy Saving strings.

### Intentionally left for later
- **Issue 4:** Admin Approvals missing from sidebar (explicitly out of scope for this pass).
- Docs / `.cursorrules` historical “Logo 2 — Energy Exchange” notes (non-UI).
- Deeper audit-status vs lead-status vocabulary on auditor decision screens (`audit_statuses.accepted` remains auditor decision language).
- Removing Mollie provider option entirely if Stripe-only (label kept for legacy rows).

### Risks / edge cases
- Wizard Continue is client-side completeness for the current step; final Submit remains server-authoritative.
- Portal users hitting `/profile` get AppLayout account settings; prefer portal-specific profile routes for day-to-day use.
- If `.env` still has `BANK_TRANSFER_ACCOUNT_NAME=RML Energy Exchange`, update locally — config default and `.env.example` are corrected.

### Key files changed
- Status/i18n: `lang/{en,es,fr}/rml.php`
- Auth/profile: `resources/js/Pages/Auth/{ConfirmPassword,VerifyEmail}.tsx`, `resources/js/Pages/Profile/Edit.tsx`
- Brand: `config/payments.php`, PDF/mail blades, WhatsApp service, `.env.example`
- Wizard: `resources/js/Pages/Seller/Leads/Create.tsx`
- Filters: `FilterBar.tsx`, `MobileFilterDrawer.tsx`, major `*/Index.tsx` listing pages, `Buyer/Leads/Index.tsx`
- Roles: `app/Support/RolePresentation.php`, `app/Enums/UserRole.php`
- Payments: `app/Support/PaymentPresentation.php`, `StripeCheckoutService.php`, Admin/Buyer payment UIs
- Types: `resources/js/types/index.d.ts`

---

## High priority issues (historical — status after this pass)

### 1. Status vocabulary split — **Fixed**
### 2. Breeze leftover auth/profile — **Fixed**
### 3. Brand Energy Exchange leftovers — **Fixed** (UI/copy)
### 4. Admin Approvals not in sidebar — **Open** (deferred)
### 5. Seller wizard validation vs step — **Fixed**
### 6. Mobile filter drawer consistency — **Fixed**
### 7. Role type synonyms — **Fixed**
### 8. Payment method vs provider labelling — **Fixed**

---

## Medium / low priority (still open)

- Messages naming (“Messages / Issues” vs “Messages”) across portals.
- Domain Foundation diagnostics in admin nav + hardcoded English.
- Seller staff invite / buyer payment success BackLink polish.
- Buyer marketplace filter density further UX polish.
- Contextual empty states with CTAs on every list.
- Align design docs that still mention Energy Exchange for logo naming.

---

## Positive findings (keep)

- Seller presenters do **not** expose `selling_price`, margin, or buyer company; buyer marketplace omits PII until release.
- Lead list filters use presentation/visible status keys.
- Destructive admin actions use app Modals (no native browser dialogs in portal code).
- Auth back-to-home pattern remains consistent via `AuthLayout`.
