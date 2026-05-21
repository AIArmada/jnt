<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Jnt\Enums\ScanTypeCode;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Services\JntStatusMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $event_hash
 * @property string $tracking_number
 * @property string|null $order_reference
 * @property string|null $scan_type_code
 * @property string|null $scan_type_name
 * @property string|null $scan_type
 * @property Carbon|null $scan_time
 * @property string|null $description
 * @property string|null $scan_network_type_name
 * @property string|null $scan_network_name
 * @property string|null $scan_network_contact
 * @property string|null $scan_network_province
 * @property string|null $scan_network_city
 * @property string|null $scan_network_area
 * @property string|null $scan_network_country
 * @property string|null $post_code
 * @property string|null $next_stop_name
 * @property string|null $next_network_province_name
 * @property string|null $next_network_city_name
 * @property string|null $next_network_area_name
 * @property string|null $remark
 * @property string|null $problem_type
 * @property string|null $payment_status
 * @property string|null $payment_method
 * @property string|null $actual_weight
 * @property string|null $longitude
 * @property string|null $latitude
 * @property string|null $time_zone
 * @property int|null $scan_network_id
 * @property string|null $staff_name
 * @property string|null $staff_contact
 * @property string|null $otp
 * @property string|null $second_level_type_code
 * @property string|null $wc_trace_flag
 * @property string|null $signature_picture_url
 * @property string|null $sign_url
 * @property string|null $electronic_signature_pic_url
 * @property array<string, mixed>|null $payload
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JntOrder|null $order
 *
 * @method static Builder<static> forOwner(?Model $owner, bool $includeGlobal = true)
 */
final class JntTrackingEvent extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'jnt.owner';

    protected static function booted(): void
    {
        static::creating(function (JntTrackingEvent $event): void {
            if ($event->order_id === null) {
                return;
            }

            // Always fetch parent order without scope to detect cross-owner writes.
            $order = JntOrder::query()->withoutOwnerScope()->find($event->order_id);

            if ($order === null) {
                throw new InvalidArgumentException('Invalid order_id for JntTrackingEvent.');
            }

            if ($event->owner_type !== null || $event->owner_id !== null) {
                if ($order->owner_type !== $event->owner_type
                    || (string) $order->owner_id !== (string) $event->owner_id) {
                    throw new InvalidArgumentException(
                        'JntTrackingEvent order_id belongs to a different owner than the current context.',
                    );
                }

                return;
            }

            $event->owner_type = $order->owner_type;
            $event->owner_id = $order->owner_id;
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_hash',
        'order_id',
        'tracking_number',
        'order_reference',
        'scan_type_code',
        'scan_type_name',
        'scan_type',
        'scan_time',
        'description',
        'scan_network_type_name',
        'scan_network_name',
        'scan_network_contact',
        'scan_network_province',
        'scan_network_city',
        'scan_network_area',
        'scan_network_country',
        'post_code',
        'next_stop_name',
        'next_network_province_name',
        'next_network_city_name',
        'next_network_area_name',
        'remark',
        'problem_type',
        'payment_status',
        'payment_method',
        'actual_weight',
        'longitude',
        'latitude',
        'time_zone',
        'scan_network_id',
        'staff_name',
        'staff_contact',
        'otp',
        'second_level_type_code',
        'wc_trace_flag',
        'signature_picture_url',
        'sign_url',
        'electronic_signature_pic_url',
        'payload',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['tracking_events'] ?? $prefix . 'tracking_events';
    }

    /**
     * Get the order that this tracking event belongs to.
     *
     * @return BelongsTo<JntOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(JntOrder::class, 'order_id');
    }

    /**
     * Check if this event represents a delivery.
     */
    public function isDelivered(): bool
    {
        $scanType = ScanTypeCode::tryFrom($this->scan_type_code ?? '');

        return $scanType === ScanTypeCode::PARCEL_SIGNED;
    }

    /**
     * Check if this event represents a pickup/collection.
     */
    public function isCollected(): bool
    {
        $scanType = ScanTypeCode::tryFrom($this->scan_type_code ?? '');

        return $scanType === ScanTypeCode::PARCEL_PICKUP;
    }

    /**
     * Check if this event represents a problem.
     */
    public function hasProblem(): bool
    {
        return $this->problem_type !== null;
    }

    /**
     * Get the normalized tracking status.
     */
    public function getNormalizedStatus(): TrackingStatus
    {
        if ($this->scan_type_code === null && $this->description === null && $this->scan_type_name === null && $this->scan_type === null) {
            return TrackingStatus::Pending;
        }

        return app(JntStatusMapper::class)->resolve(
            scanTypeCode: $this->scan_type_code,
            statusDescription: $this->description ?? $this->scan_type_name ?? $this->scan_type,
        );
    }

    /**
     * Get the location string.
     */
    public function getLocation(): string
    {
        $parts = array_filter([
            $this->scan_network_area,
            $this->scan_network_city,
            $this->scan_network_province,
            $this->scan_network_country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scan_time' => 'datetime',
            'scan_network_id' => 'integer',
            'payload' => 'array',
        ];
    }
}
