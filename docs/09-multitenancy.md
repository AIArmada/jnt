---
title: Multitenancy
---

# Multitenancy

The JNT package fully supports multi-tenant applications through the `commerce-support` package's owner scoping system. This allows you to isolate shipping orders by tenant (store, team, organization, etc.).

---

## Overview

All JNT models use the `HasOwner` trait, which provides:

- **Automatic scoping** - Queries are filtered by owner
- **Owner inheritance** - Child records inherit owner from parent
- **Global records** - Support for shared/global records
- **Filament integration** - Works with Filament's tenancy

### Owner-Enabled Models

| Model | Owns | Inherits From |
|-------|------|---------------|
| `JntOrder` | Primary owner | - |
| `JntOrderItem` | Via order | `JntOrder` |
| `JntOrderParcel` | Via order | `JntOrder` |
| `JntTrackingEvent` | Via order | `JntOrder` |
| `JntWebhookLog` | Standalone | - |

---

## Configuration

Enable owner scoping in your configuration:

```php
// config/jnt.php

'owner' => [
    // Enable owner-based scoping. Default is false (single tenant, global
    // reads/writes by design). Multi-tenant deployments MUST set this true.
    'enabled' => env('JNT_OWNER_ENABLED', false),

    // Include global (owner=null) records in queries
    'include_global' => env('JNT_OWNER_INCLUDE_GLOBAL', false),

    // Auto-assign owner when creating records
    'auto_assign_on_create' => env('JNT_OWNER_AUTO_ASSIGN', true),
],
```

### Environment Variables

```env
JNT_OWNER_ENABLED=true
JNT_OWNER_INCLUDE_GLOBAL=false
JNT_OWNER_AUTO_ASSIGN=true
```

---

## Database Schema

All owner-enabled tables have morph columns:

```php
// Migration
Schema::create('jnt_orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    // ... other columns
    $table->nullableMorphs('owner');
    $table->timestamps();
});
```

The `nullableMorphs('owner')` creates:
- `owner_type` (nullable string) - The owner model class
- `owner_id` (nullable string) - The owner model ID

---

## Setting the Owner Context

### Using OwnerContext (Recommended)

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Scope all operations to one tenant
OwnerContext::withOwner($tenant, function () {
    // Now all queries are automatically scoped
    $orders = JntOrder::query()->get(); // Only this tenant's orders

    // Create order with automatic owner assignment
    $order = JntExpress::createOrder($data); // Owner auto-assigned
});
```

### Using OwnerResolverInterface

Bind an owner resolver in your service provider:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OwnerResolverInterface::class, function () {
            return new class implements OwnerResolverInterface {
                public function resolve(): ?Model
                {
                    // Return current tenant from your tenancy system
                    return Filament::getTenant();
                    
                    // Or from session/auth
                    return auth()->user()?->team;
                }
            };
        });
    }
}
```

### With Filament Tenancy

The package integrates with Filament's built-in tenancy:

```php
// In your Filament panel provider
->tenant(Team::class)

// The owner is automatically resolved from Filament::getTenant()
```

---

## Querying with Owner Scope

### Automatic Scoping (Default-On)

When `owner.enabled` is `true`, all queries are automatically scoped:

```php
// Queries only the current owner's orders
$orders = JntOrder::query()->get();

// Same as
$orders = JntOrder::query()->forOwner(OwnerContext::resolve())->get();
```

### Explicit Owner Scoping

Use `forOwner()` to explicitly scope queries:

```php
use AIArmada\Jnt\Models\JntOrder;

// Query specific owner's orders
$orders = JntOrder::query()
    ->forOwner($tenant)
    ->get();

// Include global records
$orders = JntOrder::query()
    ->forOwner($tenant, includeGlobal: true)
    ->get();

// Query only global records (no owner)
$globalOrders = JntOrder::query()
    ->globalOnly()
    ->get();
```

> **info**
> `forOwner(null, includeGlobal: true)` is not the way to reach global rows. `null` means global-only, so use `globalOnly()` to select ownerless records.

### Bypassing Owner Scope

For admin or system operations, bypass the owner scope:

```php
use AIArmada\CommerceSupport\Support\OwnerScope;

// Bypass owner scope (use with caution!)
$allOrders = JntOrder::query()
    ->withoutGlobalScope(OwnerScope::class)
    ->get();

// Or using the convenience method
$allOrders = JntOrder::query()
    ->withoutOwnerScope()
    ->get();
```

> **Warning**: Bypassing owner scope exposes all tenant data. Use only for legitimate cross-tenant operations like admin dashboards or system jobs.

---

## Creating Records with Owner

### Automatic Owner Assignment

When `auto_assign_on_create` is enabled:

```php
OwnerContext::withOwner($tenant, function () use ($orderData) {
    // Owner is automatically set
    $order = JntExpress::createOrder($orderData);
    // $order->owner_type = Team::class
    // $order->owner_id = $tenant->id
});
```

### Manual Owner Assignment

Passing an owner tuple that does not match the current context is rejected. Set the
context to the owner you are writing for:

```php
$order = OwnerContext::withOwner($team, fn () => JntOrder::create([
    'order_id' => 'ORDER-123',
    'customer_code' => config('jnt.customer_code'),
    'owner_type' => Team::class,
    'owner_id' => $team->id,
]));
```

> **info**
> `customer_code` is `NOT NULL` in `jnt_orders` with no default, so it must be supplied on every insert. Owner columns are immutable after creation — editing `owner_type`/`owner_id` on a persisted row throws.

### Owner Inheritance

Child records automatically inherit owner from their parent:

```php
// When tracking events are created, they inherit from the order
$order = JntOrder::query()->first();

// Events inherit owner from order
foreach ($order->trackingEvents as $event) {
    // $event->owner_type === $order->owner_type
    // $event->owner_id === $order->owner_id
}
```

---

## Service Layer Usage

### JntExpressService

The service respects owner context:

```php
use AIArmada\Jnt\Facades\JntExpress;

// Scope operations to this owner
OwnerContext::withOwner($tenant, function () use ($data) {
    // All operations are scoped to this owner
    $order = JntExpress::createOrder($data);
    $tracking = JntExpress::trackParcel(null, 'JT123456');
});
```

### JntTrackingService

```php
use AIArmada\Jnt\Services\JntTrackingService;

$service = app(JntTrackingService::class);

// Get orders needing tracking update for specific owner
$orders = $service->getOrdersNeedingTrackingUpdateForOwner(
    owner: $tenant,
    includeGlobal: false,
    limit: 100
);

// Sync those orders
$results = $service->batchSyncTracking($orders);
```

> **warning**
> `getOrdersNeedingTrackingUpdate()` and `batchSyncTracking()` have no owner argument. `getOrdersNeedingTrackingUpdate()` resolves the ambient owner and throws `AuthorizationException` when `jnt.owner.enabled` is `true` and no owner is resolved, so background work must iterate owners explicitly and call `getOrdersNeedingTrackingUpdateForOwner()`. `syncOrderTracking()` re-enters `OwnerContext::withOwner()` from the order's own tuple, so it is safe to call directly.

---

## Filament Resources

### Owner-Scoped Resources

Filament resources are automatically owner-scoped:

```php
// JntOrderResource.php
class JntOrderResource extends Resource
{
    // getEloquentQuery() uses the automatic owner scope
    // No additional configuration needed for basic tenancy
}
```

### Custom Query Scoping

For advanced scenarios:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->forOwner(Filament::getTenant(), includeGlobal: false);
}
```

---

## Console Commands & Jobs

### Jobs

Jobs must explicitly handle owner context. The `OwnerContextJob` trait from
`commerce-support` resolves the owner and wraps the handler in
`OwnerContext::withOwner()` for you — implement `performJob()`, not `handle()`:

```php
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Jnt\Services\JntTrackingService;

class SyncJntTracking implements ShouldQueue
{
    use OwnerContextJob;

    public function __construct(
        public readonly string $ownerType,
        public readonly string $ownerId
    ) {}

    public function performJob(): void
    {
        // The owner is already resolved by the trait
        $service = app(JntTrackingService::class);
        $service->batchSyncTracking($service->getOrdersNeedingTrackingUpdate(limit: 100));
    }
}
```

For a hand-rolled job, resolve the owner and scope the work yourself:

```php
class SyncJntTracking implements ShouldQueue
{
    public function __construct(
        private readonly string $ownerType,
        private readonly string $ownerId
    ) {}

    public function handle(): void
    {
        $owner = OwnerContext::fromTypeAndId($this->ownerType, $this->ownerId);

        OwnerContext::withOwner($owner, function (): void {
            $service = app(JntTrackingService::class);
            $service->batchSyncTracking($service->getOrdersNeedingTrackingUpdate(limit: 100));
        });
    }
}
```

### Commands

Commands should iterate over owners:

```php
class SyncAllJntTracking extends Command
{
    public function handle(): void
    {
        // Get all active tenants
        Team::query()->chunk(100, function ($teams) {
            foreach ($teams as $team) {
                OwnerContext::withOwner($team, function () use ($team) {
                    $service = app(JntTrackingService::class);
                    $results = $service->batchSyncTracking(
                        $service->getOrdersNeedingTrackingUpdate(limit: 50)
                    );

                    $this->info("Synced {$team->name}: " . count($results['successful']));
                });
            }
        });
    }
}
```

---

## Events with Owner

Events carry owner information for cross-tenant safety:

```php
use AIArmada\Jnt\Events\JntOrderStatusChanged;

class HandleStatusChanged
{
    public function handle(JntOrderStatusChanged $event): void
    {
        // Get owner type and ID
        $ownerType = $event->ownerType;
        $ownerId = $event->ownerId;
        
        // Resolve owner model
        $owner = $event->owner();
        
        // Resolve order with proper scoping
        $order = $event->resolveOrder();

        if ($owner !== null) {
            // Enter the owner's context for any further operations
            OwnerContext::withOwner($owner, function () use ($order) {
                // ... operate on $order inside the owner's context
            });
        }
    }
}
```

---

## Webhooks

Webhook processing respects owner context:

```php
// When a webhook is received, the order is looked up with owner scoping
$order = JntOrder::query()
    ->where('tracking_number', $billCode)
    ->first();

// Tracking events inherit owner from the order
$trackingEvent = JntTrackingEvent::create([
    'order_id' => $order->id,
    'owner_type' => $order->owner_type,
    'owner_id' => $order->owner_id,
    // ...
]);
```

---

## Global Records

Records with `owner_type = null` and `owner_id = null` are **global** — they belong
to no tenant, not to every tenant. Creating one is a privileged write that needs an
explicit global context:

```php
// Create a global template order
$globalOrder = OwnerContext::withOwner(null, fn () => JntOrder::create([
    'order_id' => 'TEMPLATE-001',
    'customer_code' => config('jnt.customer_code'),
    'owner_type' => null,
    'owner_id' => null,
]));

// Query with global records included
$orders = JntOrder::query()
    ->forOwner($tenant, includeGlobal: true)
    ->get();

// Query only global records
$globalOrders = JntOrder::query()
    ->globalOnly()
    ->get();
```

> **warning**
> Global rows are never returned by an owner-scoped query unless `jnt.owner.include_global` is `true`, and they are not reachable by passing a different owner. Mutating a persisted global row requires an explicit global context, and owner tuples can never be promoted, demoted, or reassigned after creation.

---

## Security Considerations

### Always Validate IDs

Never trust client-submitted IDs without validation:

```php
// Bad - no owner validation
$order = JntOrder::find($request->order_id);

// Good - owner-scoped lookup
$order = JntOrder::query()
    ->forOwner(Filament::getTenant())
    ->findOrFail($request->order_id);
```

### Filament Action Validation

In Filament actions, always validate ownership:

```php
->action(function (array $data, $record) {
    // Verify the record belongs to current tenant
    $order = JntOrder::query()
        ->forOwner(Filament::getTenant())
        ->whereKey($record->getKey())
        ->firstOrFail();
    
    // Now safe to proceed
    JntExpress::cancelOrder($order->order_id);
})
```

---

## Testing

### Test with Owner Context

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

it('creates orders for the current owner', function () {
    $team = Team::factory()->create();

    $order = OwnerContext::withOwner($team, fn () => JntExpress::createOrder($data));

    expect($order->owner_type)->toBe(Team::class)
        ->and($order->owner_id)->toBe($team->id);
});

it('isolates orders between owners', function () {
    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();

    // Create order for team1
    OwnerContext::withOwner($team1, fn () => JntExpress::createOrder($data));

    // Query from team2 context
    $orders = OwnerContext::withOwner($team2, fn () => JntOrder::query()->get());

    expect($orders)->toBeEmpty();
});
```

---

## Summary

| Feature | Configuration | Default |
|---------|---------------|---------|
| Owner scoping | `jnt.owner.enabled` | `false` |
| Include global records | `jnt.owner.include_global` | `false` |
| Auto-assign on create | `jnt.owner.auto_assign_on_create` | `true` |

Enable multitenancy for production:

```env
JNT_OWNER_ENABLED=true
JNT_OWNER_INCLUDE_GLOBAL=false
JNT_OWNER_AUTO_ASSIGN=true
```
