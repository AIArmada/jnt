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
 * @property string $name
 * @property string|null $english_name
 * @property string|null $description
 * @property int $quantity
 * @property int $weight_grams
 * @property string $unit_price
 * @property string $currency
 * @property array<string, mixed>|null $metadata
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JntOrder $order
 *
 * @method static Builder<static> forOwner(?Model $owner, bool $includeGlobal = true)
 */
final class JntOrderItem extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'jnt.owner';

    protected static function booted(): void
    {
        static::creating(function (JntOrderItem $item): void {
            // Always fetch the parent order without scope to allow cross-owner detection.
            $order = JntOrder::query()->withoutOwnerScope()->find($item->order_id);

            if ($order === null) {
                throw new InvalidArgumentException('Invalid order_id for JntOrderItem.');
            }

            if ($item->owner_type !== null || $item->owner_id !== null) {
                // Owner was already set (e.g. by auto-assign from context).
                // Validate that the parent order belongs to the same owner.
                if ($order->owner_type !== $item->owner_type
                    || (string) $order->owner_id !== (string) $item->owner_id) {
                    throw new InvalidArgumentException(
                        'JntOrderItem order_id belongs to a different owner than the current context.',
                    );
                }

                return;
            }

            // Propagate owner from parent order.
            $item->owner_type = $order->owner_type;
            $item->owner_id = $order->owner_id;
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'name',
        'english_name',
        'description',
        'quantity',
        'weight_grams',
        'unit_price',
        'currency',
        'metadata',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['order_items'] ?? $prefix . 'order_items';
    }

    /**
     * Get the order that owns this item.
     *
     * @return BelongsTo<JntOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(JntOrder::class, 'order_id');
    }

    /**
     * Get the weight in kilograms.
     */
    public function getWeightInKilograms(): float
    {
        return $this->weight_grams / 1000;
    }

    /**
     * Get the total price for this item.
     */
    public function getTotalPrice(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'weight_grams' => 'integer',
            'metadata' => 'array',
        ];
    }
}
