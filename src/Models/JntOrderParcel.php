<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $order_id
 * @property int $sequence
 * @property string $tracking_number
 * @property string|null $actual_weight
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property array<string, mixed>|null $metadata
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JntOrder $order
 *
 * @method static Builder<static> forOwner(?Model $owner, bool $includeGlobal = true)
 */
final class JntOrderParcel extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'jnt.owner';

    protected static function booted(): void
    {
        static::creating(function (JntOrderParcel $parcel): void {
            // Always fetch parent order without scope to detect cross-owner writes.
            $order = JntOrder::query()->withoutOwnerScope()->find($parcel->order_id);

            if ($order === null) {
                throw new InvalidArgumentException('Invalid order_id for JntOrderParcel.');
            }

            if ($parcel->owner_type !== null || $parcel->owner_id !== null) {
                if ($order->owner_type !== $parcel->owner_type
                    || (string) $order->owner_id !== (string) $parcel->owner_id) {
                    throw new InvalidArgumentException(
                        'JntOrderParcel order_id belongs to a different owner than the current context.',
                    );
                }

                return;
            }

            $parcel->owner_type = $order->owner_type;
            $parcel->owner_id = $order->owner_id;
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'sequence',
        'tracking_number',
        'actual_weight',
        'length',
        'width',
        'height',
        'metadata',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['order_parcels'] ?? $prefix . 'order_parcels';
    }

    /**
     * Get the order that owns this parcel.
     *
     * @return BelongsTo<JntOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(JntOrder::class, 'order_id');
    }

    /**
     * Get the volume (length * width * height) in cubic centimeters.
     */
    public function getVolume(): ?float
    {
        if ($this->length === null || $this->width === null || $this->height === null) {
            return null;
        }

        return (float) $this->length * (float) $this->width * (float) $this->height;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'metadata' => 'array',
        ];
    }
}
