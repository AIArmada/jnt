---
title: Overview
---

# J&T Express Integration

A comprehensive Laravel package for integrating J&T Express Malaysia shipping services into your e-commerce applications.

## Features

- **Order Management** - Create, track, cancel, and print waybills for shipments
- **Batch Operations** - Process multiple orders in parallel using Laravel Concurrency
- **Real-time Tracking** - Track parcels with detailed event history
- **Webhook Integration** - Receive real-time tracking updates via Spatie Laravel Webhook Client
- **Multi-tenancy Support** - Built-in owner scoping for SaaS applications
- **Cart Integration** - Automatic shipping rate calculation for cart packages
- **Shipping Abstraction** - Implements unified shipping driver interface
- **Artisan Commands** - CLI tools for configuration, health checks, and operations
- **Notifications** - Built-in notification classes for order status updates
- **Comprehensive Events** - Laravel events for all shipping lifecycle stages

## Architecture

### Package Structure

```
packages/jnt/
├── config/
│   └── jnt.php                 # Configuration file
├── database/migrations/        # Database migrations
├── src/
│   ├── Actions/               # Action classes (CreateOrder, CancelOrder, etc.)
│   ├── Builders/              # OrderBuilder for fluent order creation
│   ├── Cart/                  # Cart shipping calculator integration
│   ├── Console/Commands/      # Artisan commands
│   ├── Data/                  # Data Transfer Objects (Spatie Laravel Data)
│   ├── Enums/                 # PHP 8.1+ enums for API values
│   ├── Events/                # Laravel events
│   ├── Exceptions/            # Custom exception classes
│   ├── Facades/               # JntExpress facade
│   ├── Health/                # Health check support
│   ├── Http/                  # HTTP client for API communication
│   ├── Listeners/             # Event listeners
│   ├── Models/                # Eloquent models
│   ├── Notifications/         # Notification classes
│   ├── Rules/                 # Laravel validation rules
│   ├── Services/              # Core service classes
│   ├── Shipping/              # Shipping driver implementation
│   ├── Support/               # Type transformers and utilities
│   └── Webhooks/              # Webhook processing
└── docs/                      # Documentation
```

### Data Flow

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Your App      │────▶│  JntExpress     │────▶│  J&T Express    │
│   (Controller)  │◀────│  Service        │◀────│  API            │
└─────────────────┘     └─────────────────┘     └─────────────────┘
                               │
                               ▼
                        ┌─────────────────┐
                        │  JntOrder       │
                        │  (Eloquent)     │
                        └─────────────────┘
```

## Models

The package provides five Eloquent models:

| Model | Description |
|-------|-------------|
| `JntOrder` | Main shipping order with sender/receiver info |
| `JntOrderItem` | Individual items in a shipment |
| `JntOrderParcel` | Multi-parcel tracking (for split shipments) |
| `JntTrackingEvent` | Individual tracking events/scans |
| `JntWebhookLog` | Webhook request logging |

All models support multi-tenancy via `HasOwner` trait from `commerce-support`.

## Enums

Type-safe PHP 8.1+ enums for J&T API values:

| Enum | Purpose |
|------|---------|
| `ExpressType` | Shipping service type (DOMESTIC, NEXT_DAY, FRESH, etc.) |
| `ServiceType` | Pickup type (DOOR_TO_DOOR, WALK_IN) |
| `PaymentType` | Payment method (PREPAID_POSTPAID, COLLECT_CASH, etc.) |
| `GoodsType` | Package content type (DOCUMENT, PACKAGE) |
| `TrackingStatus` | Normalized tracking states |
| `ScanTypeCode` | J&T API scan type codes |
| `CancellationReason` | Order cancellation reasons |
| `ErrorCode` | J&T API error codes with descriptions |

## Events

The package dispatches events at key lifecycle points:

- `OrderCreatedEvent` - New order created successfully
- `OrderCancelledEvent` - Order was cancelled
- `TrackingUpdatedEvent` - Tracking information updated
- `JntOrderStatusChanged` - Order status changed
- `ParcelPickedUp` - Parcel collected by courier
- `ParcelInTransit` - Parcel in transit
- `ParcelOutForDelivery` - Out for final delivery
- `ParcelDelivered` - Successfully delivered

## Artisan Commands

```bash
# Check configuration
php artisan jnt:config:check

# Health check
php artisan jnt:health:check

# Create order
php artisan jnt:order:create {order-id}

# Track order
php artisan jnt:order:track {order-id}

# Cancel order
php artisan jnt:order:cancel {order-id}

# Print waybill
php artisan jnt:order:print {order-id}

# Test webhook
php artisan jnt:webhook:test
```

## Requirements

- PHP 8.4+
- Laravel 11+
- `commerce-support` package for multi-tenancy
- `spatie/laravel-data` for DTOs
- `spatie/laravel-webhook-client` for webhooks

## Related Packages

- **filament-jnt** - Filament admin panel integration
- **shipping** - Unified shipping abstraction layer
- **cart** - Shopping cart with shipping support
- **commerce-support** - Multi-tenancy and shared utilities
