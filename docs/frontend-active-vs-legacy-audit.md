# Frontend Active vs Legacy Audit

Date: 2026-04-06

## Why Carrier Pool did not appear in the screenshot

The dashboard UI is served by the Inertia frontend, but the built bundle being served was stale.

- Old built layout chunk did **not** include the `stego.carriers` nav item.
- Source code **does** include Carrier Pool in the layout nav.
- Frontend assets were rebuilt with `pnpm build`, producing a new `AuthenticatedLayout` chunk that includes Carrier Pool.

Result: after a hard refresh, Carrier Pool should appear in top navigation.

## Active Frontend (source of truth)

These are the files/routes the Laravel app actually uses for authenticated web UI:

- Entry point:
  - `resources/js/app.tsx`
- Root blade host for Inertia:
  - `resources/views/app.blade.php`
- Main app layout/nav:
  - `resources/js/Layouts/AuthenticatedLayout.tsx`
- Dashboard page:
  - `resources/js/Pages/Dashboard.tsx`
- Stego Inertia pages:
  - `resources/js/Pages/Stego/Index.tsx`
  - `resources/js/Pages/Stego/Encode.tsx`
  - `resources/js/Pages/Stego/Decode.tsx`
  - `resources/js/Pages/Stego/Tokens.tsx`
  - `resources/js/Pages/Stego/CarrierPool.tsx`
- Supporting Stego hooks/utilities used by active pages:
  - `resources/js/hooks/useCarrierPool.ts`
  - `resources/js/hooks/usePreflightVerification.ts`
  - `resources/js/hooks/useCarrierManagement.ts`
  - `resources/js/utils/carrierCalculations.ts`
- Active web routes (Inertia):
  - `routes/web.php` (`/dashboard`, `/stego/*`)
- Active API routes used by active UI:
  - `routes/api.php` (`/api/stego/carriers`, `/api/stego/preflight`, `/api/stego/*`)

## Legacy Frontend (not the default app UI)

These files are a separate standalone SPA shell and should be treated as legacy unless you intentionally serve `/stego-app`:

- Legacy route:
  - `routes/web.php` route name `stego.spa` at `/stego-app/{any?}`
- Legacy blade shell:
  - `resources/views/stegolock.blade.php`
- Legacy React app:
  - `resources/js/stegolock-spa/main.tsx`
  - `resources/js/stegolock-spa/pages/*`
  - `resources/js/stegolock-spa/components/*`

## Team Rule (single source of truth)

For normal product work, edit only:

- `resources/js/Pages/**`
- `resources/js/Layouts/**`
- `resources/js/hooks/**`
- `resources/js/utils/**`
- matching Inertia/API routes in `routes/web.php` and `routes/api.php`

Avoid feature work in `resources/js/stegolock-spa/**` unless the task explicitly targets `/stego-app`.

## Operational note

After frontend/nav changes, run:

- `pnpm build`

Then hard refresh browser (`Ctrl+F5`) to avoid stale cached chunks.
