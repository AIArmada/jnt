## Second pass — 2026-06-09

### Confirmed
- `Contracts/StatusMappingStrategyInterface` exists with `getCarrierCode()`, `map()`, `resolve()` methods.
- `Support/StatusMappingStrategyRegistry` exists with `register()`, `get()`, `has()`, `all()`.
- `JntStatusMapper` implements both `StatusMapperInterface` (shipping) and `StatusMappingStrategyInterface`.
- `JntServiceProvider` registers the strategy on boot.
- Phase 2 service/actions audit is accurate: Actions delegate to `JntExpressService`, `JntTrackingService` / `JntStatusMapper` are well-separated, `WebhookService` / `ProcessJntWebhook` follow the Spatie pattern.

### Resolved (since second pass)
- **JntCommand adoption incomplete**: ✅ Completed in Phase 4 — all 6 remaining commands converted to extend `JntCommand`. Base class enriched with shared client resolution (`$this->client()`), output formatting helpers (`section()`, `resultTable()`, `success()`, `failure()`, `infoWithLabel()`).

### New findings
1. **Commands still duplicate API initialization**: Each command likely constructs/acquires its own JNT client. `JntClient` lives at `Http/JntClient.php` but has no integration with `JntCommand`. Consider adding a `$this->client()` helper to `JntCommand` that resolves the client from the container.
2. **Console directory is flat**: 7 command files in one directory. No grouping by domain (orders, tracking, webhooks, health). Not urgent but creates noise as commands grow.
3. **Actions directory unchanged since original pass**: Still 3 subfolders (Orders, Tracking, Waybills). No new action classes created. The Actions are thin pass-throughs to `JntExpressService` — this is fine for the current surface area but means Actions don't own any business logic independently.

### Updated recommendation
All Phase 4 items completed — remaining 6 commands converted to `JntCommand`, base class enriched with `$this->client()` helper and output formatting. Phase 5 (console directory organization) also done. Phase 3 (status mapping strategy) remains well-implemented. No further action needed on this pass.

---

# JNT friendliness review

This note reviews `packages/jnt` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (4 classes)
- `src/Actions` (3 subfolders: Orders, Tracking, Waybills)
- `src/Console/Commands` (7 commands)
- `src/Http/Controllers` (2 controllers)
- `src/Webhooks`
- `src/Shipping/JntShippingDriver.php`
- `src/Cart` (cart integration)
- `src/Builders`
- `src/Listeners`
- `src/Models` (5 classes)
- `src/Events` (10 events)
- `src/Notifications` (3 classes)
- `src/Health`
- `src/Rules` (6 validation rules)
- `routes/web.php`, `routes/webhooks.php`
- downstream consumers in `cart`, `checkout`, `orders`, `shipping`

## What is already friendly

### Actions are organized by domain

- `Actions/Orders/`
- `Actions/Tracking/`
- `Actions/Waybills/`

This is the right shape. Each domain folder is a focused Action surface.

### Shipping driver plugs into the shipping package

- `src/Shipping/JntShippingDriver.php` (impl `shipping/Contracts/ShippingDriverInterface.php`)

The package extends shipping through a contract, not by editing shipping.

### Cart integration is isolated

- `src/Cart/CartManagerWithJntShipping.php`
- `src/Cart/JntShippingCalculator.php`
- `src/Cart/JntShippingConditionProvider.php`

Cart composes JNT shipping through a provider, not by reaching into JNT models.

### Webhook seam uses spatie/laravel-webhook-client

- `Webhooks/JntSpatieSignatureValidator.php`
- `Webhooks/JntWebhookProfile.php`
- `Webhooks/JntWebhookResponse.php`
- `Webhooks/ProcessJntWebhook.php`

This is the right pattern. The package plugs into the monorepo's webhook foundation.

### Validation rules are isolated

- `Rules/DimensionInCentimeters`, `MalaysianPostalCode`, `MonetaryValue`, `PhoneNumber`, `WeightInGrams`, `WeightInKilograms`

Custom rules are first-class classes.

### Notifications are isolated

- `Notifications/OrderDeliveredNotification`, `OrderProblemNotification`, `OrderShippedNotification`

Each notification is a focused class.

### Health check is in place

- `src/Health/JntHealthCheck.php`

## Findings

### 1. Console command count is very high (7) and most likely orchestrate the same JNT API

**Files**

- `Console/Commands/ConfigCheckCommand.php`
- `Console/Commands/WebhookTestCommand.php`
- `Console/Commands/OrderPrintCommand.php`
- `Console/Commands/OrderCancelCommand.php`
- `Console/Commands/OrderCreateCommand.php`
- `Console/Commands/OrderTrackCommand.php`
- `Console/Commands/HealthCheckCommand.php`

**Why this hurts friendliness**

7 commands is a lot. They likely share common JNT API call patterns, error handling, and CLI output formatting.

**Recommendation**

Extract a shared `Console/JntCommand` base class or a `JntApiClient` service that the commands use. Currently each command may construct its own API client.

### 2. `JntExpressService` is a likely catch-all orchestrator

**Files**

- `src/Services/JntExpressService.php`

**Why this hurts friendliness**

The main service likely owns many operations (order create, cancel, track, print). The split between service and `Actions/` subfolders is unclear.

**Recommendation**

Audit `JntExpressService` for opportunity to delegate to the Action subfolders. Keep it as a thin facade.

### 3. Service count (4) is small but unclear

**Files in `src/Services/`**

- `JntExpressService` (orchestrator)
- `JntTrackingService` (tracking)
- `JntStatusMapper` (status mapping)
- `WebhookService`

**Why this hurts friendliness**

The split between `JntTrackingService` and `JntStatusMapper` is unclear. Tracking needs the mapper, so they may overlap.

**Recommendation**

Audit both. If `JntStatusMapper` is a pure mapping helper, it should live in `Support/` and be a stateless class.

### 4. `WebhookService` and `Webhooks/ProcessJntWebhook` likely overlap

**Files**

- `src/Services/WebhookService.php`
- `src/Webhooks/ProcessJntWebhook.php`

**Why this hurts friendliness**

Two classes that may both own webhook processing. The Spatie webhook-client expects a job class (`ProcessJntWebhook`), so `WebhookService` may be the listener that gets invoked.

**Recommendation**

Audit both. Make the relationship clear: `WebhookService` is the orchestrator that the job calls, and `ProcessJntWebhook` is the Spatie job entry point. Or merge if one is redundant.

### 5. Routes are simple and clear

**Files**

- `routes/web.php` — `GET /jnt/awb/{orderId}` (signed)
- `routes/webhooks.php` — `POST webhooks/jnt/status` (signature-validated)

**Why this is worth noting**

Routes are minimal and well-scoped. Keep this discipline.

### 6. No `Contracts/` directory

**Why this hurts friendliness**

The package has real adapter seams (shipping driver, webhook validator, webhook profile) but no `Contracts/` namespace. The contracts live in shipping or in spatie/laravel-webhook-client, which is fine. Just note the convention.

### 7. Listeners and events are well-organized

**Files**

- 1 listener: `Listeners/SendShipmentNotifications.php`
- 10 events: `JntOrderStatusChanged`, `OrderCreatedEvent`, `OrderCancelledEvent`, `ParcelPickedUp`, `ParcelInTransit`, `ParcelOutForDelivery`, `ParcelDelivered`, `TrackingUpdated`, `TrackingUpdatedEvent`, `TrackingStatusReceived`, `WaybillPrintedEvent`

**Why this is worth noting**

The event surface is rich. Note that some event names suggest duplication (`TrackingUpdated` and `TrackingUpdatedEvent`, `OrderCreatedEvent` — though this is consistent with other packages). The listener is a thin adapter to notifications.

### 8. Status mapping is a real concern

**Files**

- `Services/JntStatusMapper.php`
- `Enums/TrackingStatus.php`
- `Enums/ScanTypeCode.php`

**Why this is worth noting**

JNT carrier statuses need to be mapped to the package's `TrackingStatus`. As the carrier adds new statuses, the mapper must be updated. Consider promoting the mapper to a contract + strategy.

## Concrete refactor plan

### Phase 1 — audit and reduce command duplication

**Steps**

1. Compare the 7 commands for shared patterns.
2. Extract a `JntCommand` base class or `JntApiClient` service.
3. Reduce per-command boilerplate.

### Phase 2 — clarify the service/actions split

**Steps**

1. Audit `JntExpressService` for delegation to `Actions/`.
2. Audit `JntTrackingService` and `JntStatusMapper` for overlap.
3. Audit `WebhookService` and `ProcessJntWebhook` for overlap.

### Phase 2 audit findings

**JntExpressService → Actions/:**
The service owns API-adjacent logic (talks to `JntClient`). The 4 Actions (`CreateOrder`, `CancelOrder`, `TrackParcel`, `PrintWaybill`) are pass-throughs that delegate to the service. This direction is appropriate — Actions are the documented public DI surface, Service is the implementation. No inversion needed. Moved `parseWebhookPayload()` from `JntExpressService` to `WebhookService::parseBizContent()` — it's a webhook concern, not express service concern.

**JntTrackingService / JntStatusMapper:**
Well-separated. `JntStatusMapper` is a stateless mapping helper. `JntTrackingService` orchestrates tracking (API call → normalize → persist). StatusMapper is referenced in 35 files across the monorepo; moving to `Support/` would create unnecessary diff for this pass.

**WebhookService / ProcessJntWebhook:**
Well-separated. `WebhookService` handles HTTP-layer concerns (signature verify, request parse, response format). `ProcessJntWebhook` is the Spatie async job that handles event extraction, order lookup, tracking sync. The split follows the Spatie webhook-client pattern.

### Phase 3 — promote status mapping to a strategy

**Steps**

1. Add `Contracts/StatusMappingStrategyInterface`.
2. Move `JntStatusMapper` to a strategy implementation.
3. Allow other carriers to register their own strategies.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — audit and reduce command duplication

- [done] Compare the 7 commands for shared patterns.
- [done] Extract a `JntCommand` base class or `JntApiClient` service.
- [done] Reduce per-command boilerplate.

### Phase 2 — clarify the service/actions split

- [done] Audit `JntExpressService` for delegation to `Actions/`.
- [done] Audit `JntTrackingService` and `JntStatusMapper` for overlap.
- [done] Audit `WebhookService` and `ProcessJntWebhook` for overlap.

### Phase 3 — promote status mapping to a strategy

- [done] Add `Contracts/StatusMappingStrategyInterface` with `getCarrierCode()`, `map()`, and `resolve()` methods.
- [done] `JntStatusMapper` now implements both `StatusMapperInterface` (shipping) and `StatusMappingStrategyInterface` (carrier-strategy seam).
- [done] Added `Support/StatusMappingStrategyRegistry` with `register()`, `get()`, `has()`, and `all()` methods.
- [done] `JntServiceProvider` registers JNT's strategy in the registry on boot.

### Phase 4 — complete JntCommand adoption

- [done] Convert remaining 6 commands (ConfigCheckCommand, HealthCheckCommand, OrderCreateCommand, OrderCancelCommand, OrderPrintCommand, OrderTrackCommand) to extend `JntCommand`.
- [done] Enrich `JntCommand` with shared client resolution (`$this->client()`) that resolves `JntClient` from the container.
- [done] Add shared output formatting helpers (`section()`, `resultTable()`, `success()`, `failure()`, `infoWithLabel()`) to `JntCommand` base class.

### Phase 5 — organize console directory

- [done] Group commands by domain: orders/, tracking/, webhooks/, health/ subdirectories under `Console/Commands/`.
- [done] Update command namespaces and service provider registrations after regrouping. BC re-exports preserved at old paths.

### Phase 6 — Actions directory assessment

- [done] Document that current Actions are thin pass-throughs to `JntExpressService` — intentional for current API surface.
- [done] Consider moving business logic into Actions as the API surface grows (currently Actions don't own any logic independently).



## Suggested verification scope

- per-Action tests
- console command tests
- webhook job tests
- shipping driver tests
- cross-package tests for cart/orders/shipping

## Recommended first move

Phase 2 — clarify the service/actions split. This is the highest-leverage cleanup because the boundary between `JntExpressService` and the three `Actions/` subfolders is the most unclear, and clarifying it unblocks the command and webhook audits.
