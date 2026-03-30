# HealthSys — Clinic & hospital management

**HealthSys** is a web application for clinics and small hospitals that want one place to register patients, run fair token queues, book appointments, bill services, track doctor earnings, and close shifts with clear cash math.

It is built for **front-desk staff**, **administrators**, **owners**, and **doctors**—each sees the tools that match their job, without wading through unrelated screens.

---

## Who it is for

| Role | What they use it for |
|------|----------------------|
| **Staff (reception)** | Daily operations: shifts, walk-ins, appointments, queues, invoices, doctor payouts |
| **Admin** | Services, doctors, users, and pricing (including doctor share percentages) |
| **Owner** | Review shift history and shift conclusions with full numbers—independent of what reception says |
| **Doctor** | Profile, today’s queue view, and personal payout / share visibility |
| **Finance manager** | User role exists for your org structure; shift-close SMS can notify a finance number. Dedicated finance dashboards may be expanded over time. |

---

## What the system does (at a glance)

### Patient & family handling

- **Phone number is the family key**: one number can represent a household; you add multiple people under that family.
- **Walk-in visits**: look up by phone, pick the patient, add services (and doctors where needed), get **automatic token numbers**, then **create and print** the invoice in one flow.
- **Appointments**: book **today’s** slots on a calendar grid (by doctor), send **SMS** confirmations when SMS is enabled, mark patients **arrived** when they show up—then the visit and billing flow continues like a walk-in.

### Token queues & waiting area

- **One queue per service (and doctor, when the service needs a doctor)** so tokens stay meaningful (“Consultation with Dr. X” vs another line).
- **Walk-ins and appointments share the same counter** for that queue—no double-booked token numbers.
- **Waiting room display**: a large, readable **token screen** (meant for a TV or kiosk browser) shows who is **now being served** and how many are **waiting**. It refreshes automatically so patients always see current information.
- **Queue control**: staff can advance the line (**call next**, **skip**, **step back once**, **re-queue** someone who was skipped) from a **phone or tablet** or from optional on-screen controls.

### Money, shifts, and oversight

- **Shifts**: open with an **opening balance**, record **expenses** during the day, then **close** with a summary: collections, doctor payouts, expenses, and **net** position.
- **Invoices**: tied to visits; printable receipts for patients.
- **Doctor shares**: each service–doctor price can include a **share percentage**; reception can **log and pay** doctor shares and doctors can see their **payout history** in their portal.
- **Owner shift view**: open any closed shift and see the same conclusion data—useful for audits and trust.

### Notifications (when configured)

- **Appointment SMS** (via VeevoTech-compatible API) when enabled.
- **Broadcast** messages to appointment holders (e.g. doctor running late).
- **Shift-close SMS** to owner and/or finance phone numbers (optional).

### Security & access

- **Login** with email and password (Laravel Fortify).
- **Role-based menus and page access** in production—reception does not see admin-only setup screens, and owners see owner reports, etc. (Developers can relax this with a config flag during testing.)

---

## What it does *not* include (today)

- No public patient self-booking website.
- No lab, pharmacy, insurance claims, or multi-branch rollups in this version.

---

## Tech stack (high level)

- **Laravel** backend, **Livewire** + **Flux UI** for interactive pages, **Tailwind CSS** for styling.
- **MySQL** or **SQLite** database (production normally uses MySQL).

For **installation on a server** (e.g. Hostinger after pushing to GitHub), see **`setup.md`**.

For **how requests, queues, and data fit together in code**, see **`how is it working.md`**.

---

## License

See the project’s `LICENSE` file if present; the starter template may use MIT—confirm for your deployment.
