---
title: Jnt Context
package: jnt
status: current
surface: gateway
family: checkout-flow
keywords:
  - jnt
  - jt-express
  - waybill
  - tracking
  - carrier
---

# Jnt Context

## Snapshot
- Composer: `aiarmada/jnt`
- Role: J&T Express MY carrier adapter: orders, waybills, tracking, webhooks on top of shipping.
- Triggers: jnt, jt-express, waybill, tracking, carrier
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-jnt`, `shipping`, `checkout`
- Paired: `filament-jnt` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-jnt/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-jnt`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: J&T shipping execution.
- Skip when: Carrier-agnostic abstraction — see shipping.
- Owner/security: Owner-scoped (all 5 models).

## Key surfaces
- Models: `JntOrder`, `JntOrderItem`, `JntOrderParcel`, `JntTrackingEvent`, `JntWebhookLog`
- Actions/Services: `Actions/Orders/CancelOrder`, `Actions/Orders/CreateOrder`, `Actions/Tracking/TrackParcel`, `Actions/Waybills/PrintWaybill`, `Services/JntExpressService`, `Services/JntStatusMapper`, `Services/JntTrackingService`, `Services/WebhookService`
- Config `jnt.php`: `orders`, `order_items`, `order_parcels`, `tracking_events`, `database`, `table_prefix`, `json_column_type`, `tables`, `environment`, `api_account`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-tracking.md`, `06-webhooks.md`, `07-batch-operations.md`, `08-events.md`, `09-multitenancy.md`, `api-reference.md`, `testing-credentials.md`
