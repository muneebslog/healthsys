# HMS — Hospital Management System
## App Info & Planning Reference
> This document is the source of truth for agents/developers working on HMS. All flows, rules, and DB design decisions are described here.

---

## 1. Overview

HMS is a clinic/hospital management system designed to handle patient registration, token-based queuing, appointments, invoicing, doctor share-outs, shift management, and finance tracking.

**User Roles:**
- `staff` — Receptionist. Handles walk-ins, appointments, tokens, invoices, shift open/close.
- `admin` — Settings, user management, token reset config, printer config, dashboard.
- `owner` — Stats, shift conclusion view, financial oversight.
- `doctor` — Views own profile, services, time availability, and personal share ledger.
- `finance_manager` — Audits expenses, shares, and profits.

---

## 2. Core Modules & Pages

### Staff Pages
- **Shift** — Open shift (enter opening balance) / Close shift (log expenses, view net)
- **Walk-In** — Register patient visit, assign services + doctors, auto-token, create & print invoice
- **Appointment** — Calendar view, book slots, mark arrived
- **Doc Share Out** — View and log doctor payout per period
- **Invoices** — View/search all invoices
- **Queues** — View current token queues per service

### Admin Pages
- **Settings** — Basic CRUDs (services, doctors, users), user doc attach, token reset settings, printer config
- **Admin Dashboard** — Overview

### Owner Pages
- **Stats** — Financial stats, shift history
- **Shift Conclusion** — View any shift's summary independently (not relying on receptionist's word)

### Doctor Pages
- **Profile** — Time availability, services offered, details
- **Share** — View own share for today / last 15 days / custom range

---

## 3. Patient Flow

### Phone Number as Identity
- One phone number links to a **family/group**.
- A family can have multiple patient members (head + relations).
- **Do not reset or re-use phone numbers** — they are the primary lookup key.

### Walk-In Flow
1. Staff enters phone number → system finds existing family or prompts `+ New Person`.
2. Staff selects patient from family members.
3. Staff selects service(s) + doctor (if applicable) → clicks `+ Add` → rows appear in table.
4. Each service row gets a token auto-assigned from the active queue.
5. Staff clicks `Create & Print` → invoice created, token slip printed.

### Appointment / Reservation Flow
1. Patient calls or comes in → books a time slot for a doctor/service.
2. Token is **reserved** (linked to patient) without a visit or invoice yet.
3. SMS is sent to patient's phone number confirming their appointment + token number.
4. When patient arrives → staff marks "Arrived" → visit + invoice creation is triggered normally.
5. Reservation uses the **same queue** as walk-ins — reserved tokens are pre-assigned slots, walk-ins fill the gaps.
6. **No overlap allowed**: reserved and walk-in tokens come from the same sequential counter.

### Token Queue on Screen
- A display screen shows the currently-being-served token per service/doctor.
- Patients can see their token and estimate wait time → reduces panic/uncertainty.

---

## 4. Queue System (Critical Logic)

### Queue Table Tracks 2 Counters
| Field | Purpose |
|---|---|
| `current_token` | Next token number to assign to incoming walk-in or reservation |
| `current_flow_token` | Token currently being served / displayed on screen |

### Queue Reset Types (set per Service)
| Type | Reset Trigger |
|---|---|
| `reset_per_shift` | Queue closes when shift closes |
| `reset_daily` | Queue closes when the first shift of a new day opens |

### Queue Open/Close Rules
- **One active queue per service at a time** — no duplicates.
- Active queue = `closed_at IS NULL AND status != 'finished'`.
- On **walk-in or appointment**: find the latest active queue for that service. If none exists → create one.
- On **shift close**: close all queues where service reset type = `reset_per_shift` (set `status = closed`, stamp `closed_at`).
- On **shift open**: check if this is the **first shift of the current date**. If yes → close all queues where service reset type = `reset_daily`.

### Preventing Overlap
- Reserved tokens and walk-in tokens pull from the **same** `current_token` counter.
- Reservation pre-increments the counter and stores the token number on the `queue_tokens` row with status `reserved`.
- Walk-in assigns next available counter value → no gap collisions.

---

## 5. Shift System

### Shift Flow
1. Staff opens shift → enters opening balance → `Open Shift` button.
2. System checks: only **one open shift at a time** (status = open).
3. During shift: staff can log expenses via `Add Expense`.
4. Staff closes shift → system shows summary:
   - Opening Balance
   - Total Invoices collected
   - Doctor Payouts
   - Expenses (itemized: e.g., Print Roll, Wires, etc.)
   - **Net** = Opening + Invoices − Doctor Payouts − Expenses
5. `Log & Close Shift` button finalizes and stamps `closed_at`.

### Owner's Independent View
- Owner can view **any shift's conclusion** directly — not dependent on receptionist's narration.
- Includes: opened by, opened at, closed at, all line items, net.

---

## 6. Doctor Share System

### Share Configuration (per ServicePrice)
- Each service-doctor pairing has a `doctor_share` percentage (e.g., Consultation 70%, Drip 60%).
- Share = `invoice service amount × doctor_share %`

### Doctor Share Out Page
- Filter by Doctor + Duration (e.g., 1 day, date range).
- **Summary section**: grouped by service → count × share per unit = subtotal → grand total.
- **Details (list)**: Token#, Patient Name, Full Price, Doc Share Amount, Time.
- `Log & Pay` button marks those records as paid and timestamps the payout.

### Share Ledger
- Every `Log & Pay` action creates an entry in a `doctor_share_ledger` table.
- Doctors can view their own ledger (daily / 15-day / custom).
- Finance manager can audit all doctor share ledger entries.

---

## 7. Finance / Expense Tracking

### Expenses
- Logged per shift via `Add Expense` (category + amount).
- Examples: Print Roll, Wires, Miscellaneous.
- Visible in shift summary and owner's stats.

### Finance Manager View
- Can see: total invoices, total expenses, total doctor payouts, net profit — filtered by date/shift.
- Can drill into individual shifts and expense line items.
- Cannot modify — read/audit only.

---

## 8. Appointments Page

### Calendar View
- **Today only** — no date picker. Appointments always use today's date since queues are created for the current day only. Future booking is out of scope for now.
- Grid view: rows = 5-minute time slots, columns = doctors.
- Slots shown only within each doctor's `start_time` → `end_time` (stored on doctors table). If doctor has no schedule set, no slots shown.
- **5-minute slot intervals** — fine-grained enough to match token flow.
- Color coding:
  - 🟢 Green = Available (`Book` button)
  - 🔴/Pink = Booked (show: T-{number}, patient name, booked time, `Mark Arrived` button)
  - 🟢 Emerald = Arrived / Invoiced (show: T-{number}, patient name, `Arrived ✓`)
  - ⚫ Grey = Used by Walk-In (walk-in token with no appointment, matched by created_at to slot)
- **Token number must be visible on every non-empty cell** — it's the primary identifier patients use.
- Filter: Doctor dropdown only (no date picker).
- Actions: Book slot → creates reserved QueueToken + Appointment + sends SMS. Mark Arrived → reserved → waiting, creates visit + invoice, opens print tab.

### Broadcast Message
- Staff can send a **message to all appointment holders** for a given day (e.g., doctor delay notice).

---

## 9. Token Screen (Display)

### Hardware
- Displayed on an **old Android TV** in the waiting area opening a browser in fullscreen.
- Use **polling every 4 seconds** (`/api/token-screen/data`) — do NOT use WebSockets, old Android TV browsers may not handle it reliably.
- `/token-screen` is a **public route** (no auth required). Optionally accept a `?queue_id=` param to filter by specific queue.

### Queue Selection Screen
On load, `/token-screen` first shows a **queue picker** — all currently active queues listed as big tappable cards:
```
┌──────────────────┐  ┌──────────────────┐
│  Dr. Ayasha      │  │  Dr. Mehdi        │
│  Consultation    │  │  Consultation     │
│                  │  │                   │
│   Tap to display │  │   Tap to display  │
└──────────────────┘  └──────────────────┘
```
Staff/admin opens the TV browser, picks the queue → full screen display starts.
Selected `queue_id` stored in the URL (`/token-screen?queue_id=3`) so the TV remembers it on refresh.

### What It Shows (after queue selected)
- **Current token being served** — massive text, dead center
- **Doctor name + Service name** — top of screen
- **Remaining count** — how many are currently `waiting` (arrived)
- ❌ No "Next token" shown — next token can change any moment as more patients arrive, showing it would mislead patients

### Layout
```
┌─────────────────────────────────┐
│   Dr. Ayasha Malik              │
│   Consultation                  │
│                                 │
│   NOW SERVING                   │
│                                 │
│        T - 4 8      (huge font) │
│                                 │
│       Waiting: 12               │
└─────────────────────────────────┘
```
Dark background, high contrast, readable from across the room.

### Three Separate Pages

| Page | Who | Purpose |
|---|---|---|
| `/token-screen?queue_id=X` | Android TV | Display only, auto-polls every 4s |
| `/queues` | Staff | List all active queues, pick one to control |
| `/queues/control/{queue_id}` | Staff (phone/tablet/PC) | Remote control — call next, skip, back, re-queue |

### Token Screen — Inline Buttons (accessible screens)
For screens that staff can physically reach (mouse/touch available), show small unobtrusive `←` `→` buttons in the corner of the token screen itself. Same API calls as the remote control page. They should not distract patients — small, low opacity, tucked in bottom corner.

### Remote Control Page — `/queues/control/{queue_id}`
For screens mounted high or in hallways where no staff is nearby. Staff opens this on their **phone** (must be logged in). Built as a **Livewire component** — real-time updates without JS, supports latest browser tech on staff phones.

#### Livewire Component: `QueueControl`
```
app/Livewire/QueueControl.php
resources/views/livewire/queue-control.blade.php
```

#### Layout — Two Sections (scrollable mobile page)

**Section 1 — Control Panel (top, always visible)**
```
┌─────────────────────┐
│  Dr. Ayasha Malik   │
│  Consultation       │
│                     │
│  Now Serving: T-48  │
│  Waiting: 5         │
│  Skipped: 2         │
│  Done: 12           │
│                     │
│  ┌───────────────┐  │
│  │  CALL NEXT ▶  │  │  ← big, green, primary
│  └───────────────┘  │
│                     │
│  ┌──────┐ ┌──────┐  │
│  │◀ BACK│ │ SKIP │  │  ← smaller, secondary
│  └──────┘ └──────┘  │
└─────────────────────┘
```

**Section 2 — Patient List (below, tabbed)**

Three tabs:
- **Waiting** — arrived patients in queue order (token_number ASC). Shows who's next.
- **All** — every token issued for this queue today (reserved + waiting + serving + done + skipped)
- **Skipped** — skipped patients only, each with a `Re-queue` button

Each row shows:
```
T-48  │ John Doe        │ 2:36 PM  │ [Re-queue]  ← skipped tab
T-52  │ Amna Bibi       │ 3:01 PM  │             ← waiting tab (in order)
T-55  │ Ahmed Khan      │ --       │             ← reserved (not arrived yet)
```

Columns: Token # | Patient Name | Arrived At | Action (if applicable)

#### Livewire Properties & Methods
```php
class QueueControl extends Component
{
    public Queue $queue;
    public string $activeTab = 'waiting'; // waiting | all | skipped

    // Polling — refresh every 4 seconds to catch walk-ins arriving
    #[Poll(4000)]

    public function callNext(): void { ... }   // call lowest waiting token
    public function skip(): void { ... }        // skip current serving → skipped
    public function previous(): void { ... }    // 1-step undo
    public function requeue(int $tokenId): void { ... } // skipped → waiting

    public function getWaitingTokensProperty() {
        // status=waiting, orderBy token_number ASC
    }
    public function getAllTokensProperty() {
        // all statuses, orderBy token_number ASC
    }
    public function getSkippedTokensProperty() {
        // status=skipped
    }
}
```

#### Notes
- Requires `auth` middleware — staff must be logged in
- `#[Poll(4000)]` keeps the list live as walk-ins arrive and get added to queue
- `callNext`, `skip`, `previous` update `queue.current_flow_token` which the TV picks up via its own polling
- Mobile-first styling, large tap targets for buttons, compact rows for the patient list

### API Endpoints for Queue Control
```
GET  /queues                           → list all active queues
GET  /queues/control/{queue_id}        → remote control page

POST /api/queues/{queue_id}/call-next  → mark current serving as done, call lowest waiting token
POST /api/queues/{queue_id}/skip       → mark current serving as skipped, call next waiting
POST /api/queues/{queue_id}/previous   → undo last call (1 step only — oops correction)
                                          flip last done → serving, current serving → waiting
POST /api/tokens/{token_id}/requeue    → flip skipped → waiting (patient came back)
```

### Back Button Logic (1-step undo only)
```php
// Find last completed token
$last = QueueToken::where('queue_id', $queueId)
    ->where('status', 'done')
    ->orderBy('completed_at', 'desc')
    ->first();

if ($last) {
    // Push current serving back to waiting
    QueueToken::where('queue_id', $queueId)
        ->where('status', 'serving')
        ->update(['status' => 'waiting', 'called_at' => null]);

    // Restore last done to serving
    $last->update(['status' => 'serving', 'completed_at' => null]);
    $queue->update(['current_flow_token' => $last->token_number]);
}
// No infinite undo — only 1 step back allowed
```

---

## 10. Queue Calling Algorithm — Dynamic Re-ordering

Token numbers are **identity numbers, not position numbers**. The queue reorders dynamically based on who has physically arrived.

### Rule
When staff calls next token → do NOT blindly increment to next issued number.
Instead:
1. Query all `queue_tokens` where `status = waiting` for this queue
2. Order by `token_number ASC`
3. Call the **lowest token number among arrived patients**
4. If nobody is `waiting` → hold, don't change `current_flow_token`

### Example
```
Tokens issued:   3, 4, 5, 6, 7
Arrived so far:  6
→ Call 6

Now 4 arrives (status flips to waiting)
→ Next call = 4  (not 7, even though 7 was next by issue order)

Now 5 arrives
→ Next call = 5
```

### QueueToken Status Flow
```
reserved  →  waiting   (patient arrives / walk-in registered)
waiting   →  serving   (staff calls them via Token Control)
serving   →  done      (consultation complete)
serving   →  skipped   (staff skips, moves to next)
```

### Call Next Logic (PHP)
```php
// 1. Mark current serving token as done
QueueToken::where('queue_id', $queueId)
    ->where('status', 'serving')
    ->update(['status' => 'done', 'completed_at' => now()]);

// 2. Get next arrived patient (lowest token number)
$next = QueueToken::where('queue_id', $queueId)
    ->where('status', 'waiting')
    ->orderBy('token_number', 'asc')
    ->first();

if ($next) {
    $next->update(['status' => 'serving', 'called_at' => now()]);
    $queue->update(['current_flow_token' => $next->token_number]);
}
```

### API for Token Screen
```
GET /api/token-screen/data?queue_id=3
Returns JSON: {
    queue_id,
    doctor_name,
    service_name,
    current_flow_token,   // token currently being served
    remaining_count       // count of status=waiting tokens
    // NO next_token — intentionally excluded, it changes dynamically as patients arrive
}

GET /api/token-screen/queues
Returns all active queues for the picker screen:
[
  { queue_id, doctor_name, service_name, remaining_count },
  ...
]
```

---

## 11. Database Schema Summary

### Core Tables

**Patients** — `id, name, gender, type (enum), relation_to_head, family_id`

**Families** — `id, phone, head_id`

**Doctors** — `id, name, specialization, phone, start_time, end_time, status, is_on_payroll, user_id (nullable FK → users)`
> `start_time` and `end_time` are `TIME` columns (e.g. `09:00`, `17:00`). Used to generate appointment slots on the calendar — only slots within this range are shown. If null, doctor has no bookable slots.
> `user_id` is nullable — a doctor profile can exist without a login. Admin links a user account to a doctor and sets that user's role to `doctor`. The link is owned by the `doctors` table. One-to-one enforced via unique constraint on `user_id`.

**Services** — `id, name, is_standalone (bool), reset_type (enum: per_shift|daily), is_active (bool)`
> `reset_type` lives here on the service, not on the queue. It defines when queues for this service get reset — on shift close (`per_shift`) or on the first shift of a new day (`daily`).

**ServicePrices** — `id, service_id, doctor_id, price, doctor_share (int%), hospital_share (int%)`
> `doctor_id` is nullable. `null` means the service is standalone (no doctor involved) — one price row with no share percentages needed. Non-null means this price+share config is specific to that doctor. Unique constraint: `(service_id, doctor_id)`.

**Visits** — `id, patient_id, doctor_id, shift_id, status`
> `shift_id` is required — every visit is explicitly tied to the shift it was created in. This powers the owner's shift conclusion view without relying on timestamp derivation.

**Visit_Services** — `id, visit_id, service_id, doctor_id, status`

**Invoice** — `id, visit_id, patient_id, total_amount, status (enum)`

**Invoice_Services** — `id, invoice_id, service_id, serviceprice_id, price, discount, final_amount`

**Shifts** — `id, opened_by (user_id), opened_at, closed_at, opening_balance, status`

**ShiftExpenses** — `id, shift_id, label, amount, created_at`

**Queues** — `id, service_id, doctor_id (nullable), status, reset_type (enum: per_shift|daily), current_token (int), current_flow_token (int), shift_id (of opener), closed_at`
> Queue is scoped per **service+doctor pair**. Each doctor gets their own independent token sequence for each service they offer. Standalone services (no doctor) use `doctor_id = null` and get their own queue. Unique active queue constraint: `(service_id, doctor_id, closed_at IS NULL)`.

**Queue_Tokens** — `id, queue_id, visit_id (nullable), patient_id (nullable), token_number, status (enum: reserved|waiting|serving|done|skipped), reserved_at, called_at, completed_at, paid_at`

**DoctorShareLedger** — `id, doctor_id, shift_id or date_range, total_share, paid_at, paid_by`

---

## 12. Key Business Rules

1. **One open shift at a time** — enforce at DB and app level.
2. **Phone number = family identity** — never reassign or reset.
3. **Token counter is shared** between walk-ins and reservations per queue — no separate counters.
4. **Queue deduplication** — before creating a queue, always check for existing active one for that service.
5. **Doctor share % is per service-doctor pair** — configured in `ServicePrices`.
6. **Doc Share Out** is independent of invoice payment — it tracks the doctor's earned share; `Log & Pay` marks it settled.
7. **Shift close triggers queue resets** for `reset_per_shift` services only.
8. **First shift of new day triggers queue resets** for `reset_daily` services.
9. **Reservation** creates a `Queue_Token` with status `reserved` and sends SMS — no invoice until arrival.
10. **Owner's shift view** is read-only, pulling from shift + expense + invoice + payout data directly.

---

## 13. SMS Notifications

- Sent on: appointment booking confirmation (includes token number + time).
- May also be used for: doctor delay broadcast to all appointment holders for the day.

---

## 14. Out of Scope (for now)
- Online patient portal / self-booking web form
- Lab / pharmacy module
- Insurance / claim management
- Multi-branch support