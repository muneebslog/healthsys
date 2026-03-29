# HMS — Database Setup Guide
## For Cursor Agent: Laravel + MySQL

> Generate all migrations, models, relationships, enums, and DB-level constraints exactly as specified here. Do not deviate from column names, types, or nullability unless explicitly noted. Reference `appinfo.md` for business logic context.

---

## Stack
- **Framework**: Laravel (latest)
- **Database**: MySQL 8+
- **ORM**: Eloquent
- **Migrations**: Laravel standard migrations (one file per table)

---

## Migration Order

Run in this exact order to satisfy foreign key dependencies:

1. `users`
2. `families`
3. `patients`
4. `doctors`
5. `services`
6. `service_prices`
7. `shifts`
8. `shift_expenses`
9. `visits`
10. `visit_services`
11. `invoices`
12. `invoice_services`
13. `queues`
14. `queue_tokens`
15. `appointments`
16. `doctor_share_ledger`

---

## Enums

Define these as PHP-backed enums in `app/Enums/`:

```php
// app/Enums/UserRole.php
enum UserRole: string {
    case Staff = 'staff';
    case Admin = 'admin';
    case Owner = 'owner';
    case Doctor = 'doctor';
    case FinanceManager = 'finance_manager';
}

// app/Enums/ShiftStatus.php
enum ShiftStatus: string {
    case Open = 'open';
    case Closed = 'closed';
}

// app/Enums/QueueResetType.php
enum QueueResetType: string {
    case PerShift = 'per_shift';
    case Daily = 'daily';
}

// app/Enums/QueueStatus.php
enum QueueStatus: string {
    case Active = 'active';
    case Closed = 'closed';
    case Finished = 'finished';
}

// app/Enums/QueueTokenStatus.php
enum QueueTokenStatus: string {
    case Reserved = 'reserved';
    case Waiting = 'waiting';
    case Serving = 'serving';
    case Done = 'done';
    case Skipped = 'skipped';
}

// app/Enums/VisitStatus.php
enum VisitStatus: string {
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';
}

// app/Enums/InvoiceStatus.php
enum InvoiceStatus: string {
    case Draft = 'draft';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}

// app/Enums/PatientType.php
enum PatientType: string {
    case Head = 'head';
    case Member = 'member';
}

// app/Enums/AppointmentStatus.php
enum AppointmentStatus: string {
    case Booked = 'booked';
    case Arrived = 'arrived';
    case UsedByWalkin = 'used_by_walkin';
    case Cancelled = 'cancelled';
}
```

---

## Migrations

### 1. users
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->enum('role', ['staff', 'admin', 'owner', 'doctor', 'finance_manager']);
    $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
    // doctor_id links a user account to a doctor profile (for doctor role)
    $table->boolean('is_active')->default(true);
    $table->rememberToken();
    $table->timestamps();
});
```

---

### 1. users
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->enum('role', ['staff', 'admin', 'owner', 'doctor', 'finance_manager']);
    $table->boolean('is_active')->default(true);
    $table->rememberToken();
    $table->timestamps();
});
```

---

### 2. families
```php
Schema::create('families', function (Blueprint $table) {
    $table->id();
    $table->string('phone', 20)->unique();
    // phone is the primary lookup key — never reassign or reuse
    $table->unsignedBigInteger('head_id')->nullable();
    // set after patients table exists — updated via separate migration
    $table->timestamps();
});
```

---

### 3. patients
```php
Schema::create('patients', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('gender', ['male', 'female', 'other']);
    $table->enum('type', ['head', 'member'])->default('member');
    $table->string('relation_to_head')->nullable();
    // e.g. "Son", "Wife", "Father" — null if type = head
    $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
    $table->timestamps();
});
```

After this migration, add a separate migration to link `families.head_id`:
```php
Schema::table('families', function (Blueprint $table) {
    $table->foreign('head_id')->references('id')->on('patients')->nullOnDelete();
});
```

---

### 4. doctors
```php
Schema::create('doctors', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('specialization')->nullable();
    $table->string('phone', 20)->nullable();
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->boolean('is_on_payroll')->default(false);
    // is_on_payroll = true means doctor is salaried, share logic may differ
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    // admin links a user account to this doctor profile via Settings
    // when linking: set user.role = 'doctor' at the same time
    // nullable — a doctor can exist without a system login account
    $table->unique('user_id');
    // one user account maps to exactly one doctor profile
    $table->timestamps();
});
```
> **Link is owned by `doctors.user_id`**, not the other way around. No `doctor_id` on `users`.
> To resolve the logged-in doctor's profile: `Doctor::where('user_id', auth()->id())->first()`
> Admin flow: create/select user → set role to `doctor` → attach to doctor profile via `user_id`.

---

### 5. services
```php
Schema::create('services', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->boolean('is_standalone')->default(false);
    // is_standalone = true means this service has no doctor attached
    $table->enum('reset_type', ['per_shift', 'daily'])->default('daily');
    // reset_type controls when the queue for this service resets
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

---

### 6. service_prices
```php
Schema::create('service_prices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
    $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
    // null doctor_id = standalone service price
    $table->unsignedInteger('price');
    // price in PKR (integer, no decimals)
    $table->unsignedTinyInteger('doctor_share')->default(0);
    // percentage e.g. 70 means 70%
    $table->unsignedTinyInteger('hospital_share')->default(100);
    // percentage e.g. 30 means 30%
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['service_id', 'doctor_id']);
    // enforces one price config per service-doctor pair
    // for standalone: unique (service_id, NULL) — MySQL handles this via nullable unique
});
```

---

### 7. shifts
```php
Schema::create('shifts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('opened_by')->constrained('users');
    $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->unsignedInteger('opening_balance')->default(0);
    $table->enum('status', ['open', 'closed'])->default('open');
    $table->timestamp('opened_at')->useCurrent();
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
});
```
> **Business rule**: Only one shift with `status = open` allowed at any time. Enforce via unique partial index or app-level check before insert.

---

### 8. shift_expenses
```php
Schema::create('shift_expenses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users');
    $table->string('label');
    // e.g. "Print Roll", "Wires", "Miscellaneous"
    $table->unsignedInteger('amount');
    $table->timestamps();
});
```

---

### 9. visits
```php
Schema::create('visits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained('patients');
    $table->foreignId('family_id')->constrained('families');
    $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
    // primary doctor for this visit (may be null for standalone-only visits)
    $table->foreignId('shift_id')->constrained('shifts');
    // required — every visit belongs to the shift it was created in
    $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
    $table->timestamps();
});
```

---

### 10. visit_services
```php
Schema::create('visit_services', function (Blueprint $table) {
    $table->id();
    $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
    $table->foreignId('service_id')->constrained('services');
    $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
    $table->foreignId('service_price_id')->constrained('service_prices');
    $table->foreignId('queue_token_id')->nullable()->constrained('queue_tokens')->nullOnDelete();
    // linked after queue_tokens table exists — use separate migration or accept nullable
    $table->enum('status', ['pending', 'serving', 'done'])->default('pending');
    $table->timestamps();
});
```
> **Note**: `queue_token_id` references `queue_tokens` which is created later. Add this FK via a separate migration after `queue_tokens` is created, or define the column as just `unsignedBigInteger` and add the constraint later.

---

### 11. invoices
```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
    $table->foreignId('patient_id')->constrained('patients');
    $table->foreignId('shift_id')->constrained('shifts');
    $table->unsignedInteger('total_amount')->default(0);
    $table->unsignedInteger('discount')->default(0);
    $table->unsignedInteger('final_amount')->default(0);
    $table->enum('status', ['draft', 'paid', 'cancelled'])->default('draft');
    $table->timestamps();
});
```

---

### 12. invoice_services
```php
Schema::create('invoice_services', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
    $table->foreignId('service_id')->constrained('services');
    $table->foreignId('service_price_id')->constrained('service_prices');
    $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
    $table->unsignedInteger('price');
    // original price at time of invoice (snapshot — do not recalculate)
    $table->unsignedInteger('doctor_share_amount')->default(0);
    // calculated: price * doctor_share% — stored as snapshot
    $table->unsignedInteger('discount')->default(0);
    $table->unsignedInteger('final_amount');
    $table->boolean('doctor_share_paid')->default(false);
    // flipped to true when Log & Pay is done for this record
    $table->timestamps();
});
```

---

### 13. queues

> **KEY RULE — Queue is per SERVICE + DOCTOR pair.**
> - Dr. Ayasha doing Consultation → her own queue (T-1, T-2, T-3...)
> - Dr. Mehdi doing Consultation → his own separate queue (T-1, T-2, T-3...)
> - Drip (standalone, no doctor) → its own queue (T-1, T-2, T-3...)
> - Same service, different doctor = DIFFERENT queue. Always.
> - `doctor_id = null` is used for standalone services (no doctor attached).
> - Unique active queue constraint: ONE queue per `(service_id, doctor_id)` where `closed_at IS NULL`.

```php
Schema::create('queues', function (Blueprint $table) {
    $table->id();
    $table->foreignId('service_id')->constrained('services');
    $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
    // null = standalone service (no doctor), non-null = specific doctor
    // EACH doctor gets their OWN queue per service — no sharing between doctors
    $table->foreignId('shift_id')->constrained('shifts');
    // shift that opened this queue
    $table->enum('status', ['active', 'closed', 'finished'])->default('active');
    $table->unsignedInteger('current_token')->default(0);
    // next token number to assign (shared by walk-ins AND reservations — no separate counters)
    $table->unsignedInteger('current_flow_token')->default(0);
    // token currently being served on the waiting area display screen
    // reset_type is NOT here — it lives on services table
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();

    // Enforce at app level before insert:
    // SELECT * FROM queues WHERE service_id=? AND doctor_id=? AND closed_at IS NULL AND status != 'finished'
    // If found → use it. If not → create new one. Never create duplicates.
});
```

---

### 14. queue_tokens
```php
Schema::create('queue_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('queue_id')->constrained('queues')->cascadeOnDelete();
    $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
    // null until patient arrives (reservation flow)
    $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
    // set at reservation time
    $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
    // linked if token came from an appointment reservation
    $table->unsignedInteger('token_number');
    $table->enum('status', ['reserved', 'waiting', 'serving', 'done', 'skipped'])->default('waiting');
    $table->timestamp('reserved_at')->nullable();
    $table->timestamp('called_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();

    $table->unique(['queue_id', 'token_number']);
    // no duplicate token numbers within a queue
});
```
> After this table is created, add the FK on `visit_services.queue_token_id`.

---

### 15. appointments
```php
Schema::create('appointments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained('patients');
    $table->foreignId('family_id')->constrained('families');
    $table->foreignId('doctor_id')->constrained('doctors');
    $table->foreignId('service_id')->constrained('services');
    $table->foreignId('queue_token_id')->nullable()->constrained('queue_tokens')->nullOnDelete();
    // token reserved for this appointment
    $table->foreignId('created_by')->constrained('users');
    $table->date('appointment_date');
    $table->time('appointment_time');
    $table->enum('status', ['booked', 'arrived', 'used_by_walkin', 'cancelled'])->default('booked');
    $table->text('notes')->nullable();
    $table->boolean('sms_sent')->default(false);
    $table->timestamps();
});
```

---

### 16. doctor_share_ledger
```php
Schema::create('doctor_share_ledger', function (Blueprint $table) {
    $table->id();
    $table->foreignId('doctor_id')->constrained('doctors');
    $table->foreignId('paid_by')->constrained('users');
    // staff/admin who clicked Log & Pay
    $table->date('period_from');
    $table->date('period_to');
    $table->unsignedInteger('total_share');
    // total amount paid in this ledger entry
    $table->timestamp('paid_at')->useCurrent();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

After creating `doctor_share_ledger`, add pivot to track which `invoice_services` rows were included:
```php
Schema::create('doctor_share_ledger_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ledger_id')->constrained('doctor_share_ledger')->cascadeOnDelete();
    $table->foreignId('invoice_service_id')->constrained('invoice_services');
    $table->timestamps();
});
```

---

## Models & Relationships

### Family
```php
class Family extends Model {
    protected $fillable = ['phone', 'head_id'];

    public function head(): BelongsTo {
        return $this->belongsTo(Patient::class, 'head_id');
    }
    public function patients(): HasMany {
        return $this->hasMany(Patient::class);
    }
    public function visits(): HasMany {
        return $this->hasMany(Visit::class);
    }
}
```

### Patient
```php
class Patient extends Model {
    protected $fillable = ['name', 'gender', 'type', 'relation_to_head', 'family_id'];

    public function family(): BelongsTo {
        return $this->belongsTo(Family::class);
    }
    public function visits(): HasMany {
        return $this->hasMany(Visit::class);
    }
    public function appointments(): HasMany {
        return $this->hasMany(Appointment::class);
    }
    public function queueTokens(): HasMany {
        return $this->hasMany(QueueToken::class);
    }
}
```

### Doctor
```php
class Doctor extends Model {
    protected $fillable = ['name', 'specialization', 'phone', 'status', 'is_on_payroll'];

    public function servicePrices(): HasMany {
        return $this->hasMany(ServicePrice::class);
    }
    public function services(): BelongsToMany {
        return $this->belongsToMany(Service::class, 'service_prices');
    }
    public function queues(): HasMany {
        return $this->hasMany(Queue::class);
    }
    public function shareLedger(): HasMany {
        return $this->hasMany(DoctorShareLedger::class);
    }
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

### Service
```php
class Service extends Model {
    protected $fillable = ['name', 'is_standalone', 'reset_type', 'is_active'];

    protected $casts = [
        'reset_type' => QueueResetType::class,
    ];

    public function prices(): HasMany {
        return $this->hasMany(ServicePrice::class);
    }
    public function queues(): HasMany {
        return $this->hasMany(Queue::class);
    }
    public function priceForDoctor(?int $doctorId): ?ServicePrice {
        return $this->prices()->where('doctor_id', $doctorId)->first();
    }
}
```

### ServicePrice
```php
class ServicePrice extends Model {
    protected $fillable = ['service_id', 'doctor_id', 'price', 'doctor_share', 'hospital_share', 'is_active'];

    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }
    public function doctor(): BelongsTo {
        return $this->belongsTo(Doctor::class);
    }
    public function getDoctorShareAmountAttribute(): int {
        return (int) round($this->price * $this->doctor_share / 100);
    }
}
```

### Shift
```php
class Shift extends Model {
    protected $fillable = ['opened_by', 'closed_by', 'opening_balance', 'status', 'opened_at', 'closed_at'];

    protected $casts = [
        'status' => ShiftStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function opener(): BelongsTo {
        return $this->belongsTo(User::class, 'opened_by');
    }
    public function closer(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by');
    }
    public function expenses(): HasMany {
        return $this->hasMany(ShiftExpense::class);
    }
    public function visits(): HasMany {
        return $this->hasMany(Visit::class);
    }
    public function invoices(): HasMany {
        return $this->hasMany(Invoice::class);
    }
    public function queues(): HasMany {
        return $this->hasMany(Queue::class);
    }

    public function isOpen(): bool {
        return $this->status === ShiftStatus::Open;
    }

    // Shift conclusion computed values
    public function totalInvoices(): int {
        return $this->invoices()->where('status', 'paid')->sum('final_amount');
    }
    public function totalExpenses(): int {
        return $this->expenses()->sum('amount');
    }
    public function totalDoctorPayouts(): int {
        return Invoice::whereIn('id', $this->invoices()->pluck('id'))
            ->join('invoice_services', 'invoices.id', '=', 'invoice_services.invoice_id')
            ->sum('invoice_services.doctor_share_amount');
    }
    public function netAmount(): int {
        return $this->opening_balance + $this->totalInvoices() - $this->totalDoctorPayouts() - $this->totalExpenses();
    }
}
```

### Queue
```php
class Queue extends Model {
    protected $fillable = [
        'service_id', 'doctor_id', 'shift_id', 'status',
        'current_token', 'current_flow_token', 'closed_at'
    ];

    protected $casts = [
        'status' => QueueStatus::class,
        'closed_at' => 'datetime',
    ];

    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }
    public function doctor(): BelongsTo {
        return $this->belongsTo(Doctor::class);
    }
    public function shift(): BelongsTo {
        return $this->belongsTo(Shift::class);
    }
    public function tokens(): HasMany {
        return $this->hasMany(QueueToken::class);
    }

    public function isActive(): bool {
        return is_null($this->closed_at) && $this->status !== QueueStatus::Finished;
    }

    // Assigns next token number and increments counter atomically
    public function assignNextToken(): int {
        // Use DB::transaction + lockForUpdate to prevent race conditions
        return \DB::transaction(function () {
            $queue = Queue::lockForUpdate()->find($this->id);
            $queue->current_token += 1;
            $queue->save();
            return $queue->current_token;
        });
    }
}
```

### QueueToken
```php
class QueueToken extends Model {
    protected $fillable = [
        'queue_id', 'visit_id', 'patient_id', 'appointment_id',
        'token_number', 'status', 'reserved_at', 'called_at', 'completed_at', 'paid_at'
    ];

    protected $casts = [
        'status' => QueueTokenStatus::class,
        'reserved_at' => 'datetime',
        'called_at' => 'datetime',
        'completed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function queue(): BelongsTo {
        return $this->belongsTo(Queue::class);
    }
    public function patient(): BelongsTo {
        return $this->belongsTo(Patient::class);
    }
    public function visit(): BelongsTo {
        return $this->belongsTo(Visit::class);
    }
    public function appointment(): BelongsTo {
        return $this->belongsTo(Appointment::class);
    }
}
```

### Visit
```php
class Visit extends Model {
    protected $fillable = ['patient_id', 'family_id', 'doctor_id', 'shift_id', 'status'];

    protected $casts = [
        'status' => VisitStatus::class,
    ];

    public function patient(): BelongsTo {
        return $this->belongsTo(Patient::class);
    }
    public function family(): BelongsTo {
        return $this->belongsTo(Family::class);
    }
    public function doctor(): BelongsTo {
        return $this->belongsTo(Doctor::class);
    }
    public function shift(): BelongsTo {
        return $this->belongsTo(Shift::class);
    }
    public function services(): HasMany {
        return $this->hasMany(VisitService::class);
    }
    public function invoice(): HasOne {
        return $this->hasOne(Invoice::class);
    }
}
```

### Invoice
```php
class Invoice extends Model {
    protected $fillable = ['visit_id', 'patient_id', 'shift_id', 'total_amount', 'discount', 'final_amount', 'status'];

    protected $casts = [
        'status' => InvoiceStatus::class,
    ];

    public function visit(): BelongsTo {
        return $this->belongsTo(Visit::class);
    }
    public function patient(): BelongsTo {
        return $this->belongsTo(Patient::class);
    }
    public function shift(): BelongsTo {
        return $this->belongsTo(Shift::class);
    }
    public function services(): HasMany {
        return $this->hasMany(InvoiceService::class);
    }
}
```

### Appointment
```php
class Appointment extends Model {
    protected $fillable = [
        'patient_id', 'family_id', 'doctor_id', 'service_id',
        'queue_token_id', 'created_by', 'appointment_date',
        'appointment_time', 'status', 'notes', 'sms_sent'
    ];

    protected $casts = [
        'status' => AppointmentStatus::class,
        'appointment_date' => 'date',
        'sms_sent' => 'boolean',
    ];

    public function patient(): BelongsTo {
        return $this->belongsTo(Patient::class);
    }
    public function doctor(): BelongsTo {
        return $this->belongsTo(Doctor::class);
    }
    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }
    public function queueToken(): BelongsTo {
        return $this->belongsTo(QueueToken::class);
    }
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

### DoctorShareLedger
```php
class DoctorShareLedger extends Model {
    protected $fillable = ['doctor_id', 'paid_by', 'period_from', 'period_to', 'total_share', 'paid_at', 'notes'];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'paid_at' => 'datetime',
    ];

    public function doctor(): BelongsTo {
        return $this->belongsTo(Doctor::class);
    }
    public function paidBy(): BelongsTo {
        return $this->belongsTo(User::class, 'paid_by');
    }
    public function items(): HasMany {
        return $this->hasMany(DoctorShareLedgerItem::class, 'ledger_id');
    }
}
```

---

## Critical Service Methods to Implement

### QueueService — getOrCreateQueue()
```php
// Find active queue for service+doctor, or create one
// MUST be wrapped in DB::transaction to prevent race conditions
public function getOrCreateQueue(int $serviceId, ?int $doctorId, int $shiftId): Queue
{
    return DB::transaction(function () use ($serviceId, $doctorId, $shiftId) {
        $queue = Queue::where('service_id', $serviceId)
            ->where('doctor_id', $doctorId)
            ->whereNull('closed_at')
            ->where('status', '!=', 'finished')
            ->lockForUpdate()
            ->first();

        if (!$queue) {
            $queue = Queue::create([
                'service_id'          => $serviceId,
                'doctor_id'           => $doctorId,
                'shift_id'            => $shiftId,
                'status'              => 'active',
                'current_token'       => 0,
                'current_flow_token'  => 0,
            ]);
        }

        return $queue;
    });
}
```

### ShiftService — closeShift()
```php
public function closeShift(Shift $shift, int $closedBy): void
{
    DB::transaction(function () use ($shift, $closedBy) {
        // Close all per_shift queues
        Queue::where('status', 'active')
            ->whereNull('closed_at')
            ->whereHas('service', fn($q) => $q->where('reset_type', 'per_shift'))
            ->update(['status' => 'closed', 'closed_at' => now()]);

        $shift->update([
            'status'    => 'closed',
            'closed_by' => $closedBy,
            'closed_at' => now(),
        ]);
    });
}
```

### ShiftService — openShift()
```php
public function openShift(int $openedBy, int $openingBalance): Shift
{
    return DB::transaction(function () use ($openedBy, $openingBalance) {
        // Enforce one open shift at a time
        $existing = Shift::where('status', 'open')->lockForUpdate()->first();
        if ($existing) {
            throw new \Exception('A shift is already open.');
        }

        // Check if first shift of today → close daily queues
        $isFirstShiftToday = !Shift::whereDate('opened_at', today())->exists();
        if ($isFirstShiftToday) {
            Queue::where('status', 'active')
                ->whereNull('closed_at')
                ->whereHas('service', fn($q) => $q->where('reset_type', 'daily'))
                ->update(['status' => 'closed', 'closed_at' => now()]);
        }

        return Shift::create([
            'opened_by'       => $openedBy,
            'opening_balance' => $openingBalance,
            'status'          => 'open',
            'opened_at'       => now(),
        ]);
    });
}
```

---

## DB-Level Notes for MySQL

- Use `InnoDB` engine (default in MySQL 8) — required for foreign keys.
- All monetary values stored as `UNSIGNED INT` in **paisa or PKR whole numbers** — no decimals.
- Use `lockForUpdate()` on any token counter increment to prevent race conditions under concurrent requests.
- The unique constraint on `(service_id, doctor_id)` in `service_prices` works with MySQL nullable unique — multiple NULLs are allowed, but `(service_id, NULL)` will only allow one null per service which is the intended behavior.
- Add index on `families.phone` (already unique, so indexed automatically).
- Add index on `shifts.status` for fast open-shift lookup.
- Add index on `queues(service_id, doctor_id, closed_at)` for queue lookup performance.
- Add index on `queue_tokens.status` for display screen queries.
- Add index on `appointments.appointment_date` for calendar queries.