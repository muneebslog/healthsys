# How HealthSys works (technical deep dive)

This document explains **architecture, data flow, and where logic lives in code**. Pair it with **`appinfo.md`** for business rules and **`README.md`** for a client-facing overview.

---

## Stack & entry points

- **Framework**: Laravel 13, PHP 8.3+.
- **UI**: Livewire 4 + Flux UI; pages are mostly **Livewire Volt / single-file components** under `resources/views/pages/`.
- **Auth**: Laravel **Fortify** (login, registration, password reset, email verification, 2FA hooks as per starter).
- **HTTP**: `routes/web.php` loads feature route files; `routes/api.php` exposes token-screen JSON.

**Bootstrap**: `bootstrap/app.php` registers `web` + `api` routes and aliases middleware `token.screen.control` → `App\Http\Middleware\TokenScreenControl`.

---

## Routing map

| Area | File | Notes |
|------|------|--------|
| Home, dashboard, invoice print | `routes/web.php` | Auth + verified for app dashboard and print |
| Token display page | `routes/web.php` | `token-screen` view — **no auth** |
| Queue control API (POST) | `routes/web.php` | Prefixed `api/`, throttled; uses `token.screen.control` middleware |
| Token screen JSON | `routes/api.php` | `TokenScreenController` — public read endpoints |
| Reception | `routes/reception.php` | Shifts, walk-in, appointments, doctor share out, queues + Livewire `QueueControl` |
| Admin | `routes/admin.php` | Services, doctors, users, service prices |
| Owner | `routes/owner.php` | Shift list and shift detail |
| Doctor | `routes/doctor.php` | Dashboard, profile, payouts, today’s queue |
| Settings | `routes/settings.php` | Profile, appearance, security |

**Health check**: Laravel’s built-in `/up` (see `bootstrap/app.php`).

---

## Configuration (`config/hms.php`)

Central HMS toggles:

- **`skip_role_page_guards`**: from `HMS_SKIP_ROLE_PAGE_GUARDS`. When `true`, any logged-in user can open reception/admin pages (dev convenience). **Production must be `false`.**
- **`clinic_name`**: `HMS_CLINIC_NAME` — used on prints/receipts.
- **`token_screen_control_secret`**: optional shared secret; allows **unauthenticated** POSTs to queue control routes if the header matches (for TVs).
- **`sms`**: VeevoTech-style API (`HMS_SMS_ENABLED`, `VEEVOTECH_*`), plus optional shift-close phones for `ShiftCloseSmsNotifier`.

---

## Roles & authorization

**Enum**: `App\Enums\UserRole` — `staff`, `admin`, `owner`, `doctor`, `finance_manager`.

**Pattern**: Individual Livewire pages call `config('hms.skip_role_page_guards')` and compare `auth()->user()->role` before rendering (see reception/admin/owner/doctor pages).

**Navigation**: `resources/views/layouts/app/sidebar.blade.php` shows Reception / Owner / Doctor / Admin groups based on the same rules.

**Finance manager**: role exists on `users.role` and in admin user CRUD; dedicated finance-only Livewire sections are minimal today—shift-close SMS can target a finance number via env.

---

## Domain models (high level)

Key Eloquent models under `app/Models/`:

- **Family** / **Patient** — phone identifies family; multiple patients per family.
- **Doctor**, **Service**, **ServicePrice** — pricing and `doctor_share` / `hospital_share` percentages per service–doctor pair.
- **Shift**, **ShiftExpense** — cash drawer lifecycle.
- **Visit**, **VisitService** — clinical encounter; linked to **shift**.
- **Invoice**, **InvoiceService** — billing lines referencing `ServicePrice` rows.
- **Queue** — per service (+ optional doctor); holds `current_token` (next to issue) and `current_flow_token` (display / serving).
- **QueueToken** — `reserved` → `waiting` → `serving` → `done` or `skipped`; re-queue flips `skipped` → `waiting`.
- **Appointment** — ties to patient, doctor, slot; interacts with queue tokens for reservations.
- **DoctorShareLedger** (+ items) — records payout batches when reception logs pay.

Enums for statuses live in `app/Enums/*` (e.g. `QueueTokenStatus`, `QueueResetType`, `ShiftStatus`).

---

## Queue calling algorithm

**Service class**: `App\Services\QueueCallingService`

- **`callNext`**: In a **DB transaction**, locks the `Queue` row (`lockForUpdate()`), marks current **serving** token **done**, then promotes the **lowest token number** among **waiting** tokens to **serving** and updates `current_flow_token`.
- **`skip`**: Serving → skipped, then promote next waiting.
- **`previous`**: One-step undo: last **done** by `completed_at` → **serving**; current **serving** → **waiting**; updates `current_flow_token`.
- **`requeue`**: **Skipped** → **waiting** for a specific token.

**HTTP API**: `App\Http\Controllers\Api\QueueControlController` delegates to `QueueCallingService`.

**Livewire**: `App\Livewire\QueueControl` binds to a `Queue` model, uses `#[Poll(4000)]` to refresh lists, and calls the same domain logic (or controller patterns) for staff phones.

**Token screen**: `App\Http\Controllers\Api\TokenScreenController` reads queue state and returns JSON for the public display; the Blade view polls this endpoint (~4s) as specified in product docs.

---

## Middleware: TV control without session

**`App\Http\Middleware\TokenScreenControl`**: For routes under `api/queues/...` and `api/tokens/...`, allows the request if either:

1. User is **authenticated**, or  
2. `X-HMS-Control-Secret` matches `config('hms.token_screen_control_secret')` when that secret is non-empty.

Otherwise returns **403**. Throttling is applied in the route group (`throttle:120,1`).

---

## SMS

**`App\Services\VeevoTechSmsService`**: Outbound SMS via VeevoTech HTTP API when enabled.

**`App\Services\ShiftCloseSmsNotifier`**: On shift close, sends summary SMS to configured owner/finance numbers (dedupes identical numbers).

Appointment booking and broadcast flows call into SMS from reception Livewire logic (see appointments page component).

---

## Shifts & queue reset rules

Business rules are specified in **`appinfo.md`** (sections on queues and shifts). In code:

- Opening/closing shifts updates **Shift** status and timestamps.
- **Queue** closure and **reset_type** (`per_shift` vs `daily` on **Service**) determine when counters reset—implemented in shift open/close and queue creation logic on reception/shift pages.

Exact closure conditions should be verified in the Livewire shift components and any dedicated actions/services as the codebase evolves.

---

## Printing

**`App\Http\Controllers\InvoicePrintController`**: Route `invoices/{invoice}/print` — generates a printable view for a given invoice (auth + verified).

---

## Frontend build

- **Vite** + Tailwind v4; entry points in `resources/js`, `resources/css`.
- Production requires **`npm run build`** output in `public/build` (gitignored).

---

## Testing

- **Pest** in `tests/`; examples include `ShiftCloseSmsNotifierTest` for SMS config behavior.
- PHPUnit 12 / Pest 4 per `composer.json`.

---

## Related internal docs

- **`appinfo.md`** — product source of truth: flows, queue rules, appointment grid behavior, token screen UX.
- **`dbplan.md`** — detailed schema notes if present in repo.

This file is meant to **orient developers**; when behavior and code diverge, treat **`appinfo.md`** as the intended spec and align code or update the spec deliberately.
