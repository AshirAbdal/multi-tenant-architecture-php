# White-Label System — Lead Flow Case Studies

## Database Design Decisions

### `leads_relation_table` — why `source` and `entry_type` were removed

`CCP_id` already identifies which tenant the relationship belongs to — `source` was redundant.

`entry_type` ENUM was removed because:
1. The UNIQUE constraint on `L_id + CCP_id` means one row per lead per tenant. A single ENUM value cannot represent multiple interaction types (e.g. the same lead subscribes then later places an order — the ENUM would be stale).
2. The relationship type is already determinable from the `orders` table — no separate column needed.

```
leads_relation_table
─────────────────────────────────────
LR_id       PK
L_id        FK → leads
CCP_id      FK → clickdigim_customers_profile
created_at  datetime
```

To determine what kind of relationship a lead has with a tenant, query `orders`:
- Orders exist for this LR_id → they purchased something
- No orders exist → they only subscribed or submitted a contact form

---

## Case 1 — New Lead Buying Featured Blog (email not in leads)

**Scenario:** A user submits the Featured Blog form for the first time.
Their email does not exist in the `leads` table.

**Step-by-step flow:**

```
User submits Featured Blog form
        │
        ▼
1. CHECK leads WHERE email = 'john@example.com'
        │
        ▼  NOT FOUND
2. INSERT into leads
        name                = "John"
        email               = "john@example.com"   ← unique key
        phone               = "+1 555 0000"
        country             = "United States"
        website_url         = "https://johnbiz.com"
        legal_business_name = null   ← not collected by Featured Blog form
        │
        ▼
3. INSERT into leads_relation_table
        L_id   = (new lead id)
        CCP_id = (tenant id)
        │  → LR_id = (new)
        ▼
4. INSERT into orders
        o_id         = (new)
        LR_id        = (from step 3)
        total_amount = 250.00
        status       = 'pending'
        │
        ▼
5. INSERT into order_items
        OI_id        = (new)
        o_id         = (from step 4)
        LR_id        = (from step 3)
        CCP_id       = (tenant id)
        service_id   = (Featured Blog service id)
        quantity     = 1
        unit_price   = 250.00
        subtotal     = 250.00
        status       = 'pending'
        item_payload = {
          "suggested_keywords": "digital marketing USA",
          "products": "Featured Blog × 1",
          "quantity": 1
        }
        │
        ▼
6. User pays via PayPal / Stripe
        │
        ▼
7. UPDATE order_items.status = 'paid'
   UPDATE orders.status      = 'paid'
   INSERT into payments (payment refs, amount, method, transaction_ref)
```

**Result:**
- `leads` → 1 new row
- `leads_relation_table` → 1 new row
- `orders` → 1 new row
- `order_items` → 1 new row
- `payments` → 1 new row on payment completion

---

## Case 2 — Email Already Exists in Leads (returning lead)

Same user, same service (Featured Blog), but their email is already in the `leads` table
because they either bought another service before OR submitted from a different tenant site.

### Sub-case A: Same tenant (same `CCP_id`)

The person already bought something from this same franchise/tenant previously.

```
1. CHECK leads WHERE email = 'john@example.com'
        │
        ▼  FOUND → L_id = 5 (existing)

2. UPDATE leads SET
        name        = "John"           ← refresh with latest submitted values
        phone       = "+1 555 0000"
        website_url = "https://johnbiz.com"
   (only common lead fields — never service-specific fields)
        │
        ▼
3. CHECK leads_relation_table WHERE L_id = 5 AND CCP_id = (this tenant)
        │
        ▼  FOUND → LR_id = 12 (reuse existing row)

4. INSERT into orders (new order linked to existing LR_id = 12)
        │
        ▼
5. INSERT into order_items (new item under the new order)
        item_payload = { ...service-specific data for Featured Blog... }
```

**Result:**
- `leads` → still 1 row (updated with latest values)
- `leads_relation_table` → still 1 row (reused)
- New order + order_item added under the existing relationship

---

### Sub-case B: Different tenant (different `CCP_id`)

The person already exists in the system (submitted from another franchise site),
now buying from a **different** tenant's site for the first time.

```
1. CHECK leads WHERE email = 'john@example.com'
        │
        ▼  FOUND → L_id = 5 (existing)

2. UPDATE leads common fields with latest submitted values

3. CHECK leads_relation_table WHERE L_id = 5 AND CCP_id = (NEW tenant)
        │
        ▼  NOT FOUND

4. INSERT into leads_relation_table
        L_id   = 5             ← same existing lead
        CCP_id = (new tenant)  ← different tenant
        │  → LR_id = 27 (new row)
        ▼
5. INSERT into orders under LR_id = 27
        │
        ▼
6. INSERT into order_items under the new order
        item_payload = { ...service-specific data for Featured Blog... }
```

**Result:**
- `leads` → still 1 row (same person, updated values)
- `leads_relation_table` → 1 NEW row (because different tenant — each tenant sees only their own LR rows)
- `orders` + `order_items` → new chain belonging to the new tenant only

Both tenants operate independently through their own `LR_id`. Neither can see the other tenant's orders.

---

## Improvement to Implement — Duplicate Pending Order Guard

**Problem:** In Case 2 Sub-case A, if the same person submits the Featured Blog form
twice in a row (page refresh, back button, slow connection retry) before completing payment,
you will get two pending orders for the same lead + same service + same tenant.

Your existing backend already handles this via `updateOrCreate` in `FeaturedBlogIntentController`.
You must mirror this logic at the **order layer** as well:

**Rule before creating a new order:**

```
CHECK orders
  WHERE LR_id     = (existing LR_id for this lead + tenant)
  AND   status    = 'pending'
  JOIN  order_items ON orders.o_id = order_items.o_id
  WHERE order_items.service_id = (Featured Blog service id)

→ IF EXISTS:
    UPDATE order_items SET
        item_payload = (new submitted payload)
        unit_price   = (latest price)
        subtotal     = (latest total)
    UPDATE orders SET
        total_amount = (latest total)
        updated_at   = now()

→ IF NOT EXISTS:
    INSERT new order + order_item (normal flow)
```

**This prevents:**
- Duplicate pending records in `orders` and `order_items`
- Admin seeing ghost unpaid orders
- Duplicate payment captures if the user completes payment on one of the duplicates

**This does NOT affect paid orders** — once `status = 'paid'` the guard skips it,
so the person can legitimately buy the same service again and get a fresh order.

---

## Case 3 — Paid Appointment Booking Flow

Covers the 4 paid appointment service types only. Case 4 covers the free appointment.
The only difference between the 4 paid services is `service_id`, `unit_price`, and `duration_minutes`.
All 5 appointment services (including free) share the same slot availability logic.

| Service               | service_id | unit_price | duration_minutes | appointment_type |
|-----------------------|------------|------------|------------------|------------------|
| Free Appointment      | 5          | $0.00      | 30               | free             |
| Quick Consultation    | 1          | $74.99     | 30               | paid             |
| Standard Meeting      | 2          | $100.00    | 60               | paid             |
| Extended Session      | 3          | $150.00    | 90               | paid             |
| Full Strategy Session | 4          | $200.00    | 120              | paid             |

---

### Critical rule — slot check happens FIRST, before touching leads or orders

The system calls `availabilityService->getAvailableSlots(date, service_id)` and
hard-stops if the slot is taken. No lead row, no order row, nothing is written
until the slot is confirmed free.

---

### Phase 1 — Booking + Deposit Payment

```
User picks service + date + time
        │
        ▼
STEP 1: CHECK slot availability
        appointments WHERE appointment_date = '2026-07-15'
                       AND appointment_time = '10:00'
                       AND service_id = 2
                       AND status != 'cancelled'
        │
        ├── SLOT TAKEN → stop, return error, show alternative slots
        │
        ▼  SLOT FREE → continue
        │
STEP 2: CHECK leads WHERE email = 'john@example.com'
        │
        ├── NOT FOUND → INSERT into leads (name, email, phone, country, website_url)
        │                 → L_id = (new)
        │
        └── FOUND → UPDATE leads common fields → L_id = (existing)
        │
        ▼
STEP 3: CHECK leads_relation_table WHERE L_id = ? AND CCP_id = (tenant)
        │
        ├── NOT FOUND → INSERT leads_relation_table
        │                 L_id, CCP_id
        │                 → LR_id = (new)
        │
        └── FOUND → reuse LR_id = (existing)
        │
        ▼
STEP 4: INSERT into appointments   ← reserves the slot immediately
        LR_id             = (from step 3)
        service_id        = 2                    ← Standard Meeting
        o_id              = null                 ← filled after payment
        appointment_date  = '2026-07-15'
        appointment_time  = '10:00'
        duration_minutes  = 60                   ← from services table
        timezone          = 'America/New_York'
        notes             = 'First strategy call'
        status            = 'pending'            ← not confirmed yet
        payment_reference = null                 ← set on deposit payment
        amount_paid       = null
        after_amount      = null
        balance_token     = null
        → apt_id = 42
        │
        ▼
STEP 5: INSERT into orders
        LR_id        = (from step 3)
        total_amount = 100.00
        status       = 'pending'
        → o_id = 88
        │
        ▼
STEP 6: INSERT into order_items
        o_id         = 88
        LR_id        = (from step 3)
        CCP_id       = (tenant)
        service_id   = 2
        quantity     = 1
        unit_price   = 100.00
        subtotal     = 100.00
        status       = 'pending'
        item_payload = {
          "appointment_id":   42,
          "appointment_date": "2026-07-15",
          "appointment_time": "10:00",
          "timezone":         "America/New_York",
          "notes":            "First strategy call",
          "duration_minutes": 60
        }
        │
        ▼
STEP 7: UPDATE appointments SET o_id = 88   ← link order back to appointment
        │
        ▼
STEP 8: User pays deposit via PayPal / Stripe
        │
        ▼
STEP 9: Payment confirmed → fulfillAppointment() runs
        UPDATE appointments SET
          paypal_payment_status  = 'paid'
          paypal_order_id        = 'PAY-xxx'
          paypal_capture_id      = 'CAP-xxx'
          amount_paid            = 50.00          ← deposit amount
          after_amount           = 50.00          ← full_price(100) - deposit(50)
          paid_at                = now()
          payment_reference      = 'APT-42'       ← shared key for both payments
          overall_payment_status = 'deposit_paid'
        │
        ▼
STEP 10: INSERT into payments
          LR_id             = (from step 3)
          o_id              = 88
          invoice_id        = (generated)
          payment_reference = 'APT-42'
          total_amount      = 50.00
          amount            = 50.00
          payment_date      = now()
          payment_method    = 'paypal'
          transaction_ref   = 'CAP-xxx'
          status            = 'paid'
        │
        ▼
STEP 11: UPDATE orders.status      = 'paid'
         UPDATE order_items.status = 'paid'
         Send deposit invoice email to client
```

**Result after Phase 1:**
- `leads` → 1 row (new or updated)
- `leads_relation_table` → 1 row (new or reused)
- `appointments` → 1 new row (slot reserved, status = pending)
- `orders` → 1 new row
- `order_items` → 1 new row with appointment payload
- `payments` → 1 new row (deposit)

---

### Phase 2 — Admin Workflow (approve / reject)

```
Admin reviews pending appointment
        │
        ├── REJECT →
        │     UPDATE appointments SET status = 'cancelled'
        │     DELETE Google Calendar event (if google_event_id exists)
        │     Slot is now FREE again for others to book
        │     ── REFUND (paid appointments only, is_paid = true) ──
        │     Issue refund via PayPal / Stripe API for amount_paid
        │     UPDATE payments SET status = 'refunded'
        │     Rejection + refund confirmation email sent to client
        │
        └── APPROVE →
              UPDATE appointments SET status = 'confirmed'
              │
              ▼
              If Google Calendar connected:
                CREATE Google Calendar event
                UPDATE appointments SET
                  google_event_id = 'gcal_event_id'
                  meeting_link    = 'https://meet.google.com/xxx'
              │
              ▼
              Send approval email to client
              (includes meeting_link if Google Meet was created)
```

---

### Phase 3 — Balance Payment (when after_amount > 0)

```
Admin requests balance payment
        │
        ▼
UPDATE appointments SET
  balance_token         = UUID (unique, secure one-time token)
  second_payment_status = 'pending'
        │
        ▼
Send balance request email to client
with link: /pay-balance/{balance_token}
        │
        ▼
Client opens link → system reads:
  appointments WHERE balance_token = ?
  Returns: service name, date, time, after_amount
        │
        ▼
Client pays balance via PayPal / Stripe
        │
        ▼
fulfillAppointmentBalance() runs
  UPDATE appointments SET
    second_payment_status     = 'paid'
    second_payment_order_id   = 'PAY-yyy'
    second_payment_capture_id = 'CAP-yyy'
    second_paid_at            = now()
    overall_payment_status    = 'completed'
        │
        ▼
INSERT into payments (second payment row)
  payment_reference = 'APT-42'   ← same shared key as deposit
  payment_method    = 'paypal'
  transaction_ref   = 'CAP-yyy'
  status            = 'paid'
        │
        ▼
Send balance invoice email to client
```

**Result after Phase 3:**
- `appointments.overall_payment_status` = `completed`
- `payments` → now has 2 rows for `payment_reference = 'APT-42'` (deposit + balance)
- Both rows traceable under a single `payment_reference` on the PayPal/Stripe dashboard

---

### Why appointments table must stay separate from order_items

Three things make collapsing it into `order_items.item_payload` impossible:

1. **Slot availability query** — `WHERE appointment_date = ? AND appointment_time = ? AND status != 'cancelled'`
   runs on every calendar page load. This must be indexed. Querying inside JSON is not indexable.

2. **Two-phase payment with two independent transaction rows** — `payment_reference = 'APT-42'`
   ties both payments together. `balance_token` must be a `UNIQUE` column for IDOR protection —
   not possible inside JSON.

3. **Post-payment state machine** — `status`, `google_event_id`, `meeting_link`,
   `second_payment_status` all change independently after the order is already paid.
   The `orders` / `order_items` tables are billing records and must not change after payment.

---

### Improvement — Slot Conflict Guard (duplicate pending booking)

**Problem:** If the same person submits for the same slot twice (page refresh, back button,
slow connection retry) before completing payment, you get two pending `appointments` rows
for the same slot — which blocks that slot for everyone else even though neither is paid.

**Rule before STEP 4 (INSERT into appointments):**

```
CHECK appointments
  WHERE LR_id          = (this lead's LR_id for this tenant)
  AND   service_id     = (selected service)
  AND   appointment_date = '2026-07-15'
  AND   appointment_time = '10:00'
  AND   status != 'cancelled'

→ IF EXISTS (same person, same slot, same tenant, still pending):
    UPDATE order_items SET
        item_payload = (latest submitted payload)
    UPDATE orders SET
        updated_at = now()
    Do NOT create a duplicate appointment row

→ IF NOT EXISTS:
    Proceed with INSERT (normal Phase 1 flow)
```

**This prevents:**
- Duplicate `appointments` rows holding the same slot hostage
- Admin seeing ghost unpaid bookings on the calendar
- Duplicate payment captures if the user completes payment on both attempts

**This does NOT affect paid appointments** — once `paypal_payment_status = 'paid'`
the guard skips it, so the person can legitimately book the same service again on a
different date and get a fresh appointment row.

---

## Case 4 — Free Appointment Booking Flow

**Key difference from Case 3:** `service_payload.is_paid = false`.
No order, no order_items, no payment, no deposit, no refund on rejection.
Admin still approves or rejects — that is the same.

```
User picks Free Appointment + date + time
        │
        ▼
STEP 1: CHECK slot availability
        appointments WHERE appointment_date = '2026-07-15'
                       AND appointment_time = '14:00'
                       AND status != 'cancelled'
        │
        ├── SLOT TAKEN → stop, show error
        │
        ▼  SLOT FREE
STEP 2: Upsert lead (email check → insert or update common fields)
STEP 3: Upsert leads_relation_table (L_id + CCP_id)
        │
        ▼
STEP 4: INSERT into appointments
        LR_id             = (from step 3)
        service_id        = (Free Appointment service id)
        o_id              = NULL         ← no order, never set
        appointment_date  = '2026-07-15'
        appointment_time  = '14:00'
        duration_minutes  = 30
        timezone          = 'America/New_York'
        notes             = 'Quick chat'
        status            = 'pending'
        amount_paid       = NULL         ← free, no payment
        after_amount      = NULL         ← free, no balance
        balance_token     = NULL         ← free, never used
        → apt_id = 43
        │
        ▼
        Admin notification email sent
        (No orders, order_items, or payments rows created)
        │
ADMIN DECISION:
        │
        ├── REJECT →
        │     UPDATE appointments SET status = 'cancelled'
        │     Slot freed
        │     No refund needed (was free)
        │     Rejection email sent to client
        │
        └── APPROVE →
              UPDATE appointments SET status = 'confirmed'
              If Google Calendar connected:
                CREATE Google Calendar event
                UPDATE appointments SET
                  google_event_id = 'gcal_xxx'
                  meeting_link    = 'https://meet.google.com/xxx'
              Approval email with meeting link sent to client
```

**Result:**
- `leads` → 1 row (new or updated)
- `leads_relation_table` → 1 row (new or reused)
- `appointments` → 1 new row (slot reserved, status = pending)
- `orders` → **NOT created**
- `order_items` → **NOT created**
- `payments` → **NOT created**

**How backend distinguishes free vs paid at runtime:**
```sql
SELECT JSON_EXTRACT(s.service_payload, '$.is_paid') AS is_paid
FROM   appointments a
JOIN   services s ON a.service_id = s.s_id
WHERE  a.apt_id = 43;
-- returns: false  →  skip all payment/refund logic
-- returns: true   →  enforce deposit + refund on rejection
```
