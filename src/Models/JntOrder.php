<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $order_id
 * @property string|null $tracking_number
 * @property string $customer_code
 * @property string $action_type
 * @property string|null $service_type
 * @property string|null $payment_type
 * @property string|null $express_type
 * @property string|null $status
 * @property string|null $sorting_code
 * @property string|null $third_sorting_code
 * @property string|null $chargeable_weight
 * @property int $package_quantity
 * @property string|null $package_weight
 * @property string|null $package_length
 * @property string|null $package_width
 * @property string|null $package_height
 * @property string|null $package_value
 * @property string|null $goods_type
 * @property string|null $offer_value
 * @property string|null $cod_value
 * @property string|null $insurance_value
 * @property CarbonInterface|null $pickup_start_at
 * @property CarbonInterface|null $pickup_end_at
 * @property CarbonInterface|null $ordered_at
 * @property CarbonInterface|null $last_synced_at
 * @property CarbonInterface|null $last_tracked_at
 * @property CarbonInterface|null $delivered_at
 * @property string|null $last_status_code
 * @property string|null $last_status
 * @property bool $has_problem
 * @property CarbonInterface|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $remark
 * @property array<string, mixed>|null $sender
 * @property array<string, mixed>|null $receiver
 * @property array<string, mixed>|null $return_info
 * @property array<string, mixed>|null $offer_fee_info
 * @property array<string, mixed>|null $customs_info
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property array<string, mixed>|null $metadata
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, JntOrderItem> $items
 * @property-read Collection<int, JntOrderParcel> $parcels
 * @property-read Collection<int, JntTrackingEvent> $trackingEvents
 * @property-read Collection<int, JntWebhookLog> $webhookLogs
 *
 * @method static Builder<static> forOwner(?Model $owner = null, bool $includeGlobal = true)
 */
final class JntOrder extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'jnt.owner';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'tracking_number',
        'customer_code',
        'action_type',
        'service_type',
        'payment_type',
        'express_type',
        'status',
        'sorting_code',
        'third_sorting_code',
        'chargeable_weight',
        'package_quantity',
        'package_weight',
        'package_length',
        'package_width',
        'package_height',
        'package_value',
        'goods_type',
        'offer_value',
        'cod_value',
        'insurance_value',
        'pickup_start_at',
        'pickup_end_at',
        'ordered_at',
        'last_synced_at',
        'last_tracked_at',
        'delivered_at',
        'last_status_code',
        'last_status',
        'has_problem',
        'cancelled_at',
        'cancellation_reason',
        'remark',
        'sender',
        'receiver',
        'return_info',
        'offer_fee_info',
        'customs_info',
        'request_payload',
        'response_payload',
        'metadata',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['orders'] ?? $prefix . 'orders';
    }

    /**
     * Get the items for this order.
     *
     * @return HasMany<JntOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(JntOrderItem::class, 'order_id');
    }

    /**
     * Get the parcels for this order.
     *
     * @return HasMany<JntOrderParcel, $this>
     */
    public function parcels(): HasMany
    {
        return $this->hasMany(JntOrderParcel::class, 'order_id');
    }

    /**
     * Get the tracking events for this order.
     *
     * @return HasMany<JntTrackingEvent, $this>
     */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(JntTrackingEvent::class, 'order_id');
    }

    /**
     * Get the webhook logs for this order.
     *
     * @return HasMany<JntWebhookLog, $this>
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(JntWebhookLog::class, 'order_id');
    }

    /**
     * Check if the order has been delivered.
     */
    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Check if the order has any problems.
     */
    public function hasProblem(): bool
    {
        return $this->has_problem;
    }

    /**
     * Get the latest tracking event.
     */
    public function latestTrackingEvent(): ?JntTrackingEvent
    {
        return $this->trackingEvents()->latest('scan_time')->first();
    }

    /**
     * Boot the model and register cascade delete handlers.
     */
    protected static function booted(): void
    {
        self::creating(function (JntOrder $order): void {
            if (! config('jnt.owner.enabled', false)) {
                return;
            }

            if (! config('jnt.owner.auto_assign_on_create', true)) {
                return;
            }

            $attributes = $order->getAttributes();

            if (array_key_exists('owner_type', $attributes) || array_key_exists('owner_id', $attributes)) {
                return;
            }

            if ($order->owner_type !== null || $order->owner_id !== null) {
                return;
            }

            $owner = $order->resolveOwner();

            if ($owner !== null) {
                $order->assignOwner($owner);
            }
        });

        self::deleting(function (JntOrder $order): void {
            // Application-level cascade delete
            $order->items()->delete();
            $order->parcels()->delete();
            $order->trackingEvents()->delete();
            $order->webhookLogs()->update(['order_id' => null]);
        });
    }

    protected function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_quantity' => 'integer',
            'has_problem' => 'boolean',
            'pickup_start_at' => 'datetime',
            'pickup_end_at' => 'datetime',
            'ordered_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_tracked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'sender' => 'array',
            'receiver' => 'array',
            'return_info' => 'array',
            'offer_fee_info' => 'array',
            'customs_info' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'metadata' => 'array',
        ];
    }
}
