# HealthSys — Features showcase

**HealthSys** is a clinic and small-hospital operations platform. It brings together front-desk workflows, fair token queues, billing, doctor earnings, shift cash control, and oversight tools in one secure web application.

This document is a **client-facing overview** of what the system offers today. Technical setup and architecture are covered elsewhere (for example `README.md` and `setup.md`).

---

## Who uses HealthSys

| Role | Purpose |
|------|---------|
| **Staff (reception)** | Day-to-day patient flow: shifts, walk-ins, lab checkout, procedures, appointments, queues, invoices, doctor share payouts |
| **Admin** | Services, lab tests, doctors, users, pricing, doctor share percentages, operational settings, logs and insights |
| **Owner** | Independent view of shift history and shift conclusions with full financial line items |
| **Doctor** | Profile and schedule context, today’s tokens, procedures, and personal payout visibility |
| **Finance manager** | Read-focused finance dashboards: money trail, expenses, payout ledger, audit views, CSV exports |

Access is **role-based**: each role sees the menus and pages that match their job. Your team can tighten or relax this in production configuration.

---

## Patient and family management

- **Phone number as the family key** — One number represents a household. You can maintain multiple people (head and relations) under the same family without duplicating contact data.
- **Walk-in registration** — Look up by phone, select the patient, add services (and doctors where required), receive **automatic token numbers** from the correct queue, then **create and print** the invoice in one flow.
- **Patient details where needed** — Flows such as lab checkout can capture age and related fields and keep them on the patient record for downstream use.

---

## Appointments

- **Same-day scheduling grid** — Book slots by doctor within configured working hours, with fine-grained time steps so the calendar matches real clinic rhythm.
- **Shared token numbering** — Reserved appointment tokens and walk-in tokens use the **same counter** for each queue, so token numbers stay unique and easy to explain to patients.
- **Arrival handling** — When the patient arrives, staff marks them arrived so the visit and billing path continues consistently with walk-ins.
- **SMS (when configured)** — Appointment confirmations can include token and timing information via your SMS provider setup.
- **Broadcast messaging** — Staff can send a message to appointment holders for the day (for example, a doctor running late).

---

## Token queues and the waiting area

- **Queues per service (and doctor when needed)** — Tokens stay meaningful: for example, “Consultation with Dr. X” is separate from another line when your services are set up that way.
- **Smart “call next”** — The system calls the **lowest token number among patients who have actually arrived**, so the line stays fair when people show up in a different order than they were issued tokens.
- **Staff queue control** — From a phone, tablet, or PC, staff can **call next**, **skip**, **step back one** (single undo), and **re-queue** skipped patients when they return.
- **Waiting room display** — A **large-format token screen** (browser on a TV or kiosk) shows **who is being served now** and **how many are waiting**. It refreshes on a steady interval so older displays stay reliable without demanding WebSocket support.
- **Queue picker** — On first load, the display can list active queues so staff tap the right doctor or service before fullscreen mode.
- **Optional remote API control** — For integrated displays, queue advance actions can be secured with a shared secret where you enable it.

---

## Lab checkout

- **Dedicated lab workflow** — Reception runs **Lab checkout** to attach catalogued tests to a patient, apply discounts where allowed, and produce billing aligned with lab visits.
- **Searchable test catalog** — Tests are maintained in admin (**Lab tests**) with codes, names, prices, and active/inactive flags.
- **External lab integration (when configured)** — The system can sync case data with a compatible external lab HMS, with **API request logging** in admin for support and troubleshooting.
- **Sample and slip handling** — Serial allocation and line allocation services help keep lab lines and paperwork consistent with invoices.

---

## Procedures (surgical / packages)

- **Procedure records** — Create and track procedures with doctor, reference, package pricing, room, dates, status, and notes.
- **Payment milestones** — Record payments against procedures as your workflow requires.
- **Doctor visibility** — Doctors have a **Processes** area to see their procedures and payment status over a date range.

---

## Billing and invoices

- **Invoices tied to visits** — Charges flow from registered visits and selected services (and lab lines where applicable).
- **Invoice list and search** — Staff and finance can browse and verify the invoice register.
- **Invoice lookup** — Fast path to find an invoice when a patient returns with a question or receipt.
- **Printable invoices** — Print-friendly invoice output for handouts and records.

---

## Shifts and cash discipline

- **Open and close shifts** — One open shift at a time; opening balance is recorded when the day starts.
- **Shift expenses** — Log categories and amounts during the shift (for example supplies or misc costs).
- **Close summary** — On close, see opening balance, collections, doctor payouts, expenses, and **net** position before finalizing.
- **Printable close summary** — Shift close can be printed for the cash drawer or management file.
- **Shift-close notifications (optional)** — SMS can notify owner and/or finance numbers when a shift closes, if you configure it.

---

## Doctor shares and payouts

- **Configurable shares** — Each **service–doctor price** can define doctor and hospital share percentages.
- **Doc share out (reception)** — Filter by doctor and period, review totals and line detail, then **log and pay** to record a payout batch.
- **Payout receipt print** — Printable receipt for payout records.
- **Ledger history** — Payout batches are stored so finance and doctors can audit what was paid and when.

---

## Finance manager suite

- **Finance dashboard** — High-level view of revenue, expenses, payouts, and implied net over time.
- **Money trail** — Ordered view of how money moved: collections, expenses, and payouts.
- **Expenses across shifts** — Expense lines consolidated for review.
- **Doctor payout ledger** — Drill into payout batches and details.
- **Audit-oriented views** — Surfaces for discounts, cancellations, shift closes, and related events worth a second look.
- **Exports** — **CSV downloads** for invoices, expenses, ledger, and shifts to feed accounting tools.

---

## Owner oversight

- **Shift list and detail** — Open any shift’s conclusion independently of reception narrative: who opened it, timing, totals, expenses, and net.

---

## Doctor portal

- **Doctor home** — Entry dashboard with context for the day.
- **Profile** — Services and schedule-related information staff and doctors rely on.
- **Today’s queue** — Tokens assigned to that doctor today.
- **My payouts** — Personal visibility into share payouts and history.
- **Processes** — Procedures and payment status for that doctor over a selected range.

---

## Administration

- **Services** — Define services, whether they need a doctor, queue reset behavior (per shift or daily), and activation.
- **Lab tests** — Maintain the lab catalog (codes, names, prices, integration fields as applicable).
- **Lab API log** — Review outbound lab API traffic for diagnostics.
- **Application log** — Operational log visibility for administrators.
- **Queue insights** — Analytics-style views on queue performance; per-queue detail where provided.
- **Consultation contacts** — Manage contact records used in appointment-related workflows.
- **Doctors** — Doctor profiles, linkage to user accounts where doctors log in, schedules for appointment grids.
- **Users** — User accounts, roles, and activation.
- **Service prices** — Prices per service (and per doctor when the service is doctor-specific), including share percentages.
- **Admin settings** — Clinic-wide toggles and configuration your deployment uses (SMS, printers, token behavior, etc., as implemented in your build).

---

## Platform, security, and personalization

- **Secure sign-in** — Email and password authentication; password reset and email verification follow Laravel Fortify conventions.
- **Two-factor authentication** — Available where enabled in your Fortify configuration.
- **Profile and appearance** — Users can adjust profile and UI appearance preferences.
- **Security settings** — Password updates and 2FA management in line with your policy.
- **Dark mode–ready interface** — Modern UI built for long front-desk sessions.

---

## Public and display-only surfaces

- **Welcome / marketing home** — Public landing content as shipped with your deployment.
- **Token screen** — Public display URL for waiting areas (no patient login required).
- **Token screen API** — JSON endpoints for custom displays or integrations that poll queue state.

---

## Summary

HealthSys is designed so **reception runs the day**, **queues stay understandable for patients**, **money and doctor shares stay traceable**, and **owners and finance can verify numbers without guesswork**. Admins keep the catalog, users, and pricing aligned with how your clinic actually works—including **lab checkout** and **procedures** alongside traditional consultation and service billing.

---

## Not in scope today (typical)

Exact roadmaps vary by deployment, but the following are commonly **not** part of the core product focus: public self-booking websites for patients, full pharmacy modules, insurance claim engines, and multi-branch consolidated reporting. Confirm with your implementation team for your contract and version.
