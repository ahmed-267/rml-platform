# RML Platform — Figma Source of Truth

Primary Figma Make file:

https://www.figma.com/make/glTwPDC5LawftaI9medKzH/Low-Fidelity-Seller-Portal-Wireframe--Copy-

This file is the **full product prototype**, not a single page. It is a clickable Figma Make app with a top-level prototype navigator. Use it as the visual and structural reference for implementation.

---

## Critical rules

1. **Figma top tabs are design references only.**  
   Do **not** implement them as real application tabs or routes.

2. Figma prototype sections that must **not** become app navigation:
   - Design System
   - Public Website
   - Authentication
   - Seller Portal
   - Buyer Portal
   - Admin Portal
   - Mobile Views
   - Flow Map

3. **Public website must remain one continuous scrollable landing page.**  
   Even when Figma shows Buy Leads, Sell Leads, Free Installation, and Contact as separate frames, the real app implements them as **anchor sections** on `/`.

4. Public navigation scrolls to:
   - Home (`#home`)
   - Buy Leads (`#buy-leads`)
   - Sell Leads (`#sell-leads`)
   - Free Installation (`#free-installation`)
   - Contact (`#contact`)

5. Login and Register remain separate real routes:
   - `/login`
   - `/register`

6. Do **not** create public routes such as:
   - `/buy-leads`
   - `/sell-leads`
   - `/free-installation`
   - `/contact`

7. Do not skip phases just because frames exist in Figma.  
   Inspect the relevant frames at the start of each phase, implement only that phase, then stop for approval.

8. Preserve the real app architecture:
   - Laravel + Inertia React + TypeScript + Tailwind
   - role-based portals and permissions
   - Untitled UI / RML visual quality
   - responsive behaviour with `useMediaQuery` for structural changes

---

## Figma section → real app mapping

| Figma prototype section | Real app area | Notes |
|-------------------------|---------------|-------|
| Design System | Global UI tokens + reusable components | Always apply |
| Public Website | `/` landing page sections | Merge frames into one page |
| Authentication | `/login`, `/register`, seller/buyer reg, pending, forgot password | Real routes |
| Seller Portal | `/seller/*` | Phase 4 |
| Buyer Portal | `/buyer/*` | Phase 5 |
| Admin Portal | `/admin/*` | Phase 6 |
| Mobile Views | Responsive behaviour across all screens | All phases + Phase 9 polish |
| Flow Map | Journey / QA reference | Not a shipped UI |

---

## Frames that are references only

These help design and QA, but are **not** shipped as screens or nav items:

- Design System page
- Mobile Views gallery
- Flow Map
- Figma top prototype navigator tabs
- Landing-page chat widget (prototype convenience only; not an MVP product requirement)
- Any “Figma-style tab strip” used to switch between Public Website frames

---

## Public Website frames → landing sections

| Figma public frame | Real landing section |
|--------------------|----------------------|
| Landing | `#home` hero + How It Works + trust strip content as needed |
| Buy Leads | `#buy-leads` |
| Sell Leads | `#sell-leads` |
| Free Installation | `#free-installation` |
| Contact | `#contact` |

Hero visual direction from Figma:
- Dark navy gradient hero
- Green accent / pill (“Audited & Validated Leads”)
- Strong headline hierarchy
- Buy / Sell / Register / Login CTAs

Market copy in the real app is **Spain-focused**, even if Figma demo text uses UK examples.

---

## Authentication frames → Phase 3 routes

| Figma auth frame | Real route / screen |
|------------------|---------------------|
| Login | `/login` |
| Registration Choice | `/register` |
| Seller Registration | `/register/seller` (full form in Phase 3) |
| Buyer Registration | `/register/buyer` (full form in Phase 3) |
| Pending Approval | `/pending-approval` (or equivalent) |
| Forgot Password | existing password reset routes |

Auth visual rules:
- Centered white card
- RML logo
- Language switcher EN / ES / FR
- Green primary CTAs
- No default Laravel/Breeze chrome as the final look

---

## Seller Portal frames → Phase 4

Sidebar screens in Figma:

1. Dashboard  
2. Submit Lead  
3. My Leads  
4. Audited Leads  
5. Payments  
6. Staff Commissions  
7. Messages  
8. Profile  

Shell pattern:
- Dark navy sidebar
- Clean top header
- White cards on light grey background
- Green CTAs

---

## Buyer Portal frames → Phase 5

Sidebar screens in Figma:

1. Dashboard  
2. Buy Leads  
3. Lead Packages  
4. Leads Bought  
5. Payments  
6. Messages  
7. Profile  

Critical Buy Leads behaviours from Figma:
- Customer details hidden before payment
- Checkbox multi-select + select all
- Sticky/mobile payment summary bar
- Distance from buyer company/warehouse address

---

## Admin Portal frames → Phase 6

Sidebar screens in Figma:

1. Dashboard  
2. Sellers  
3. Buyers  
4. Users  
5. Leads Bought  
6. Leads Sold  
7. Payments  
8. Messages / Issues  
9. Reports  
10. Settings  

Important:
- **Do not** add “Audit Leads” as a main sidebar item.
- Audit Lead is an overlay/modal/form opened from Sellers, action queue, or lead detail.

Settings tabs in Figma:
- Schemes
- Zones
- Zone Pricing
- Pricing Algorithm
- Commission Rules
- Agreement Templates
- T&C Templates
- GDPR Templates
- General Settings

---

## Mobile Views → responsive rules

Figma mobile references:

1. Public Homepage  
2. Login  
3. Seller Submit Lead  
4. Buyer Buy Leads (+ payment summary)  
5. Admin Dashboard  
6. Audit Lead Form  

Responsive rules for the real app:

- Mobile-first Tailwind utilities for styling
- `useMediaQuery` only when structure must change
- Sidebar → mobile drawer
- Wide tables → stacked cards / mobile card lists
- Forms → single column on small screens
- Modals → drawer/bottom sheet where better on mobile
- Buyer payment summary → sticky bottom on mobile
- No horizontal overflow
- Large tap targets and readable text

---

## Flow Map → journey reference

Use for QA and implementation order validation, not as a UI page.

Key flows documented in Figma:
- Landing → Register choice → Seller/Buyer registration → Pending approval
- Login → Seller / Buyer / Admin dashboards
- Seller submit lead → My Leads
- Admin audit lead → listed for buyers
- Buyer select + pay → Leads Bought (details released)
- Admin pay seller → seller payments
- Seller pay staff commissions
- Admin settings configuration

---

## Design system tokens and component rules

### Logo
- **Selected for real app:** Logo 2 — RML Energy Exchange  
  (house outline + exchange arrows + energy bolt)
- Figma Design System currently marks Logo 3 as selected in-prototype; **ignore that for product** and keep Logo 2.

### Colours
| Token | Hex |
|-------|-----|
| Primary Green | `#16a34a` |
| Primary Light | `#dcfce7` |
| Primary Lighter | `#f0fdf4` |
| Sidebar Navy | `#0f172a` |
| Blue | `#2563eb` |
| Blue Light | `#eff6ff` |
| Amber | `#d97706` |
| Red | `#dc2626` |
| Text | `#111827` |
| Muted | `#6b7280` |
| Border | `#e5e7eb` |
| Background | `#f8fafc` |
| Card | `#ffffff` |

### Typography
- Primary font: Inter
- Mono font for IDs (`LD-1041`, `PAY-00091`, `TXN-9921`)
- Clear hierarchy: page title bold, section title semibold, compact table text

### Component style
- Premium modern SaaS / Untitled UI quality
- Light mode only for MVP
- White cards, subtle borders/shadows, rounded corners
- Green primary CTAs
- Compact status badges
- Clean tables and KPI cards
- Consistent form inputs, alerts, modals, drawers

### Portal shell
- Dark navy sidebar for logged-in portals
- Clean sticky header
- Light grey page background
- White content cards

### Do not ship
- Generic default Laravel auth look as final UI
- Generic unstyled shadcn appearance unless heavily restyled to RML/Untitled UI
- Public pricing exposure
- Figma prototype top navigation in the real product

---

## Frames to use per implementation phase

| Phase | Goal | Figma sections to inspect |
|-------|------|---------------------------|
| **1 / 1.5** | Foundation + landing alignment | Design System, Public Website |
| **2** | Database / domain foundation | Design System + entity needs from Seller/Buyer/Admin flows (no UI build) |
| **3** | Public/auth completion | Authentication (+ Public Website polish if needed) |
| **4** | Seller Portal | Seller Portal + Mobile Submit Lead |
| **5** | Buyer Portal | Buyer Portal + Mobile Buy Leads / Payment Summary |
| **6** | Super Admin Portal | Admin Portal + Audit Lead overlay + Mobile Admin/Audit |
| **7** | Internal Auditor Portal | Not present in Figma — extend from Admin Audit Lead patterns |
| **8** | Payments, invoices, WhatsApp, notifications | Payment flows across Seller/Buyer/Admin |
| **9** | Responsive polish, tests, QA | Mobile Views + Flow Map |

---

## Known gaps vs Figma

- **Internal Auditor / Staff portal** is required in MVP but not present as a Figma section. Build in Phase 7 using Admin audit-form patterns with reduced permissions.
- Figma demo data often uses UK names/addresses; product market is **Spain**.
- Figma Design System logo selection differs from product rule (Logo 2). Product rule wins.
- Figma Public Website uses multiple frames; real app uses one landing page.

---

## Working process for every phase

1. Inspect the relevant Figma frames through MCP.  
2. Summarise what was found.  
3. Implement **only** that phase.  
4. Stop and wait for approval before the next phase.

Do not rewrite working code unless needed to match Figma or fix bugs.
