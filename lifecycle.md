# JNT Package — Lifecycle Audit & Refactoring Plan

## 1. Executive Summary

The JNT package models a J&T Express Malaysia shipping lifecycle across 4 dedicated tables plus a
shared `webhook_calls` table. The current schema uses a `has_problem` boolean flag and a `status`
string column without enum casting. This plan replaces `has_problem` with `problem_at` timestamp,
hardens `status` to a `TrackingStatus` enum cast, and adds business-critical lifecycle timestamps
for tracking events. No backward compatibility.

Key scope:
- `JntOrder` — replace `has_problem` boolean with `problem_at` timestamp, add `exception_at` /
  `returned_at` / `resolved_at`, harden `status` to `TrackingStatus` enum cast.
- `JntTrackingEvent` — no persistence changes (append-only event log).
- `JntWebhookLog` — ensure `processed_at` column is explicitly added by JNT migration.

---

## 2. Full Inventory by Table

### 2.1 `jnt_orders`

| Column | Current Type | Lifecycle Role | Problem |
|--------|-------------|----------------|---------|
| `id` | uuid PK | — | — |
| `order_id` | varchar(50) unique | Carrier-given ID | — |
| `tracking_number` | varchar(30) nullable unique | Carrier tracking code | — |
| `customer_code` | varchar(30) indexed | Tenant discriminator | — |
| `action_type` | varchar(30) default 'add' | API action | — |
| `service_type` | varchar(30) nullable indexed | Service level | — |
| `payment_type` | varchar(30) nullable indexed | Payment method | — |
| `express_type` | varchar(30) nullable indexed | Express level | — |
| **`status`** | **varchar(50) nullable indexed** | **Lifecycle status** | **P1: Raw string, no enum cast. Written as `TrackingStatus->value`.** |
| `sorting_code` | varchar(64) nullable | — | — |
| `third_sorting_code` | varchar(64) nullable | — | — |
| `chargeable_weight` | decimal(10,3) nullable | — | — |
| `package_quantity` | int default 1 | — | — |
| `package_weight` / `_length` / `_width` / `_height` | decimal | — | — |
| `package_value` | decimal(12,2) nullable | — | — |
| `goods_type` | varchar(30) nullable | — | — |
| `offer_value` | decimal(12,2) nullable | — | — |
| `cod_value` | decimal(12,2) nullable | — | — |
| `insurance_value` | decimal(12,2) nullable | — | — |
| `pickup_start_at` | timestampTz nullable | Pickup window start | OK |
| `pickup_end_at` | timestampTz nullable | Pickup window end | OK |
| `ordered_at` | timestampTz nullable | Carrier order creation time | OK |
| `last_synced_at` | timestampTz nullable | Last API sync | OK |
| `last_tracked_at` | timestampTz nullable | Last tracking event received | OK |
| `delivered_at` | timestampTz nullable indexed | Delivered timestamp | OK |
| `cancelled_at` | timestampTz nullable indexed | Cancelled timestamp | OK |
| `cancellation_reason` | varchar(255) nullable | Reason | OK |
| `last_status_code` | varchar(32) nullable indexed | Raw carrier scan code | OK |
| `last_status` | varchar(128) nullable | Raw carrier status text | OK |
| **`has_problem`** | **boolean default false indexed** | **Problem flag** | **P0: Should be `problem_at timestampTz`** |
| `remark` | text nullable | — | — |
| `sender` / `receiver` / `return_info` / `offer_fee_info` / `customs_info` | jsonb nullable | — | — |
| `request_payload` / `response_payload` | jsonb nullable | — | — |
| `metadata` | jsonb nullable | — | — |
| `owner_type` / `owner_id` | nullableMorphs | Tenant boundary | OK |
| `created_at` / `updated_at` | timestampsTz | — | OK |

**Missing timestamp columns:**
- `problem_at timestampTz nullable` — replaces `has_problem` boolean
- `exception_at timestampTz nullable` — first exception/problem timestamp
- `returned_at timestampTz nullable` — when return was completed
- `resolved_at timestampTz nullable` — when problem was resolved

**Missing model cast:**
- `status` → should cast to `TrackingStatus` enum

### 2.2 `jnt_order_items`

| Column | Current Type | Lifecycle Role | Problem |
|--------|-------------|----------------|---------|
| `id` | uuid PK | — | — |
| `order_id` | foreignUuid indexed | Parent order | — |
| Various item columns | — | — | — |
| `owner_type` / `owner_id` | nullableMorphs | Propagated from parent | OK |
| `created_at` / `updated_at` | timestampsTz | — | OK |

**No issues:** Items are immutable after creation in a shipping context. No lifecycle changes needed.

### 2.3 `jnt_order_parcels`

| Column | Current Type | Lifecycle Role | Problem |
|--------|-------------|----------------|---------|
| `id` | uuid PK | — | — |
| `order_id` | foreignUuid indexed | Parent order | — |
| Physical dimension columns | — | — | — |
| `owner_type` / `owner_id` | nullableMorphs | Propagated from parent | OK |
| `created_at` / `updated_at` | timestampsTz | — | OK |

**No issues:** J&T API does not support per-parcel lifecycle tracking. Parcels inherit lifecycle
from the parent order. No parcel-level columns added.

### 2.4 `jnt_tracking_events`

| Column | Current Type | Lifecycle Role | Problem |
|--------|-------------|----------------|---------|
| Various payload columns | — | — | — |
| `scan_type_code` | varchar(32) nullable indexed | Carrier scan code | OK |
| `scan_time` | timestampTz nullable indexed | Event time | OK |
| `problem_type` | varchar(128) nullable indexed | Problem indicator | OK |
| `owner_type` / `owner_id` | nullableMorphs | Propagated from parent | OK |
| `created_at` / `updated_at` | timestampsTz | — | OK |

**No issues:** Tracking events are append-only snapshots. Runtime methods `hasProblem()` and
`isDelivered()` are correct as computed properties.

### 2.5 `webhook_calls` (shared table, extended by JNT migration)

| Column | Type | Lifecycle Role | Problem |
|--------|------|----------------|---------|
| Various webhook columns | — | — | — |
| `processing_status` | varchar(32) default 'pending' | Processing lifecycle | OK |
| `processed_at` | timestamp nullable | When processed | P3: May not be explicitly added by JNT migration |
| `owner_type` / `owner_id` | nullableMorphs | Added by JNT migration | OK |

### 2.6 Enums

| Enum | Values | Role | Status |
|------|--------|------|--------|
| `TrackingStatus` | pending, picked_up, in_transit, at_hub, out_for_delivery, delivery_attempted, delivered, return_initiated, returned, exception | Order lifecycle | Well-defined, has isTerminal/isSuccessful helpers |
| `ScanTypeCode` | 10, 20, 30, 94, 100, 110, 172, 173, 200-201, 300-306, 400-405 | Carrier event codes | Mapped to TrackingStatus via JntStatusMapper |
| `CancellationReason` | customer_*, out_of_stock, etc. | Cancellation reasons | OK |
| `ServiceType` / `ExpressType` / `GoodsType` / `PaymentType` | various | Categorization enums | OK (not lifecycle) |
| `ErrorCode` | 1, 0, 145003xxx, 999xxx | API errors | OK |

---

## 3. Problems Summary

### P0 — Replace `has_problem` boolean with `problem_at timestampTz`

**Affects:** `JntOrder` model, migration, `JntTrackingService::syncOrderTracking()`,
`ProcessJntWebhook`

**Current:** `has_problem` (boolean, default false) set to `true` when a problem scan arrives.

**Required:** `problem_at timestampTz nullable` — set when first problem detected. Allows:
- Knowing *when* the problem occurred (not just that it exists)
- Tracking *resolution* (set `resolved_at`, null `problem_at`)
- Querying "currently problematic" vs "ever had a problem"

### P1 — Harden `JntOrder.status` to `TrackingStatus` enum cast

**Affects:** `JntOrder` model casts

**Current:** `status` is a raw `varchar(50)` nullable string. Written as `TrackingStatus->value`
but the model doesn't enforce/cast it.

**Required:** Add `'status' => TrackingStatus::class` to `casts()`.

### P2 — Add missing lifecycle timestamps to `jnt_orders`

| Timestamp | Rationale |
|-----------|-----------|
| `exception_at` | First time an exception/problem occurred (terminal: never cleared) |
| `returned_at` | JNT orders can be returned (ScanTypeCode::RETURN_SIGN → returned) |
| `resolved_at` | When a problem was resolved |

### P3 — Ensure `processed_at` column exists on `webhook_calls`

The JNT migration does not explicitly add `processed_at`. Should be checked/added explicitly.

---

## 4. Recommended Structure

### 4.1 `jnt_orders` target schema (lifecycle-relevant columns only)

```sql
CREATE TABLE jnt_orders (
    id uuid PRIMARY KEY,
    -- ... existing columns ...
    status varchar(50) NULL,            -- TrackingStatus enum, cast in model
    -- REMOVED: has_problem boolean
    pickup_start_at timestamptz NULL,
    pickup_end_at timestamptz NULL,
    ordered_at timestamptz NULL,
    last_synced_at timestamptz NULL,
    last_tracked_at timestamptz NULL,
    delivered_at timestamptz NULL,
    cancelled_at timestamptz NULL,
    problem_at timestamptz NULL,        -- NEW: replaces has_problem
    exception_at timestamptz NULL,      -- NEW
    returned_at timestamptz NULL,       -- NEW
    resolved_at timestamptz NULL,       -- NEW
    -- ... rest unchanged ...
);
```

### 4.2 Lifecycle state machine

```
                    ┌──────────┐
                    │ Pending  │
                    └────┬─────┘
                         │ ParcelPickup (10)
                    ┌────▼──────┐
                    │ PickedUp  │
                    └────┬──────┘
                         │ Outbound (20) / InTransit events
                    ┌────▼──────┐
                    │ InTransit │
                    └────┬──────┘
                         │ Arrival (30) / Inbound Hub events
                    ┌────▼───────┐
                    │  AtHub     │
                    └────┬───────┘
                         │ DeliveryScan (94)
                    ┌────▼──────────┐
                    │ OutForDelivery │
                    └────┬───────────┘
                         │ ParcelSigned (100)
                    ┌────▼──────┐
                    │ Delivered │ ← Terminal (success)
                    └───────────┘
                         │ Problematic (110) / Damage (201) / Lost (300) / Dispose (301) / Reject (302)
                    ┌────▼──────┐
                    │ Exception │ ← Terminal (failure)
                    └────┬──────┘
                         │ ReturnScan (172) / ReturnSign (173)
                    ┌────▼──────────────┐
                    │ ReturnInitiated → Returned │ ← Terminal (return)
                    └───────────────────┘

Cancellation (independent):
    Any non-terminal → Cancelled (via API cancelOrder)
```

Timestamps populated at each transition:
- `pickup_start_at` / `pickup_end_at` — set at order creation
- `ordered_at` — set when carrier acknowledges order
- `problem_at` — set on first Exception/problem scan, nulled on resolution
- `exception_at` — set on first Exception scan (terminal: never cleared)
- `delivered_at` — set on ParcelSigned (100)
- `cancelled_at` — set on API cancellation
- `returned_at` — set on ReturnSign (173)
- `resolved_at` — set when problem resolved

### 4.3 `JntOrder` model — new casts

```php
protected function casts(): array
{
    return [
        // ... existing ...
        'status' => TrackingStatus::class,
        // ... rest unchanged ...
    ];
}
```

---

## 5. Refactoring Plan — Parallel-Agent Checklist

### Agent A: Migration — jnt_orders lifecycle columns

**Input:** `packages/jnt/database/migrations/`

- [x] **A1.** Create new migration: drop `has_problem`, add `problem_at`, `exception_at`,
  `returned_at`, `resolved_at`

```php
Schema::table($ordersTable, function (Blueprint $table): void {
    $table->dropColumn('has_problem');
    $table->timestampTz('problem_at')->nullable()->after('delivered_at');
    $table->timestampTz('exception_at')->nullable()->after('problem_at');
    $table->timestampTz('returned_at')->nullable()->after('exception_at');
    $table->timestampTz('resolved_at')->nullable()->after('returned_at');
});
```

- [x] **A2.** Backfill `problem_at` from rows where `has_problem` was true:

```php
DB::table($ordersTable)
    ->where('has_problem', true)
    ->update(['problem_at' => DB::raw('updated_at')]);
```

- [x] **A3.** Ensure `processed_at` column is explicitly added to `webhook_calls` table (if not
  already present).

### Agent B: JntOrder model updates

**Input:** `packages/jnt/src/Models/JntOrder.php`

- [x] **B1.** Remove `has_problem` from `$fillable` and casts
- [x] **B2.** Add `problem_at`, `exception_at`, `returned_at`, `resolved_at` to `$fillable`
- [x] **B3.** Update `casts()`:
  - Remove `'has_problem' => 'boolean'`
  - Add `'status' => TrackingStatus::class`
  - Add `'problem_at' => 'datetime'`
  - Add `'exception_at' => 'datetime'`
  - Add `'returned_at' => 'datetime'`
  - Add `'resolved_at' => 'datetime'`
- [x] **B4.** Update PHPDoc `@property` tags to match
- [x] **B5.** Replace `hasProblem()` method body: `return $this->problem_at !== null;`
- [x] **B6.** Add `isReturned(): bool` and `isCancelled(): bool` methods

### Agent C: Service/Webhook updates — replace `has_problem` references

**Input:** `packages/jnt/src/Services/JntTrackingService.php`,
`packages/jnt/src/Webhooks/ProcessJntWebhook.php`

- [x] **C1.** `JntTrackingService::syncOrderTracking()`: replace `$order->has_problem = true` with
  `$order->problem_at = CarbonImmutable::now()` (only if `$order->problem_at === null`)
- [x] **C2.** `ProcessJntWebhook::syncShipmentTrackingFromWebhook()`: replace
  `$updates['has_problem'] = true` with `$updates['problem_at']` logic
- [x] **C3.** Add `exception_at` population (set on first `Exception` status if null)
- [x] **C4.** Add `returned_at` population (set on `Returned` status if null)
- [x] **C5.** Add resolution logic: set `resolved_at` and null `problem_at` when a non-problem
  scan arrives after a problem

### Agent D: Tests

**Input:** `packages/jnt/tests/`

- [x] **D1.** Migration test: verify new columns exist, `has_problem` is gone
- [x] **D2.** Model test: verify `status` casts to `TrackingStatus` enum
- [x] **D3.** Lifecycle test: order moves through states, verify `*_at` timestamps
- [x] **D4.** Problem/resolution test: exception sets `problem_at`/`exception_at`, resolution
  clears `problem_at` and sets `resolved_at`
- [x] **D5.** Cross-tenant scoping test with new columns

### Agent E: Verification

- [x] **E1.** Run `./vendor/bin/pint packages/jnt/src` on changed files
- [x] **E2.** Run `./vendor/bin/phpstan analyse packages/jnt/src --level=6`
- [x] **E3.** Run `./vendor/bin/pest --parallel packages/jnt/tests`

---

## 6. Migration Strategy

### 6.1 Column migration

Single migration for `jnt_orders`. Backfill `problem_at` from existing `has_problem` data before
dropping the column. No data loss since `has_problem = true` maps to `problem_at = updated_at`.

### 6.2 Order of operations

1. Run Agent A (migration)
2. Run Agents B, C in parallel (model + service updates)
3. Run Agent D (tests)
4. Run Agent E (verification)

### 6.3 Rollback

No `down()` required per guidelines. New migration is additive except for `has_problem` drop.

---

## 7. Verification Commands

```bash
# 1. Check no FK constraints in migrations
rg -n -- "constrained\(|cascadeOnDelete\(" packages/jnt/database

# 2. Check no remaining has_problem references in src
rg -n -- "has_problem" packages/jnt/src packages/jnt/config packages/jnt/database

# 3. Check new columns referenced in models
rg -n -- "problem_at|exception_at|returned_at|resolved_at" packages/jnt/src/Models

# 4. Format
./vendor/bin/pint packages/jnt/src packages/jnt/database

# 5. Static analysis
./vendor/bin/phpstan analyse packages/jnt/src --level=6

# 6. Tests
./vendor/bin/pest --parallel packages/jnt/tests

# 7. Migration dry-run
php artisan migrate --pretend --path=packages/jnt/database/migrations

# 8. Verify status enum cast
rg -n -- "'status' => TrackingStatus" packages/jnt/src/Models
```

### Acceptance Criteria

- [x] `has_problem` column does not exist on `jnt_orders`
- [x] `problem_at`, `exception_at`, `returned_at`, `resolved_at` exist as `timestampTz nullable` on `jnt_orders`
- [x] `JntOrder.status` casts to `TrackingStatus` enum
- [x] `hasProblem()` checks `problem_at !== null`
- [x] Tracking/webhook sync sets `problem_at` instead of `has_problem = true`
- [x] Resolution logic nulls `problem_at` and sets `resolved_at`
- [x] All existing tests pass
- [x] New lifecycle tests pass
- [x] PHPStan level 6 clean
