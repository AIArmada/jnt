<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $tracking_number
 * @property string|null $order_reference
 * @property string|null $digest
 * @property array<string, mixed>|null $headers
 * @property array<string, mixed>|null $payload
 * @property string $processing_status
 * @property string|null $processing_error
 * @property Carbon|null $processed_at
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JntOrder|null $order
 *
 * @method static Builder<static> forOwner(?Model $owner, bool $includeGlobal = true)
 */
final class JntWebhookLog extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'jnt.owner';

    protected static function booted(): void
    {
        static::creating(function (JntWebhookLog $log): void {
            if ($log->order_id === null) {
                return;
            }

            // Always fetch parent order without scope to detect cross-owner writes.
            $order = JntOrder::query()->withoutOwnerScope()->find($log->order_id);

            if ($order === null) {
                throw new InvalidArgumentException('Invalid order_id for JntWebhookLog.');
            }

            if ($log->owner_type !== null || $log->owner_id !== null) {
                if ($order->owner_type !== $log->owner_type
                    || (string) $order->owner_id !== (string) $log->owner_id) {
                    throw new InvalidArgumentException(
                        'JntWebhookLog order_id belongs to a different owner than the current context.',
                    );
                }

                return;
            }

            $log->owner_type = $order->owner_type;
            $log->owner_id = $order->owner_id;
        });
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'tracking_number',
        'order_reference',
        'digest',
        'headers',
        'payload',
        'processing_status',
        'processing_error',
        'processed_at',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['webhook_logs'] ?? $prefix . 'webhook_logs';
    }

    /**
     * Get the order that this webhook log belongs to.
     *
     * @return BelongsTo<JntOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(JntOrder::class, 'order_id');
    }

    /**
     * Check if the webhook has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->processing_status === self::STATUS_PROCESSED;
    }

    /**
     * Check if the webhook processing failed.
     */
    public function isFailed(): bool
    {
        return $this->processing_status === self::STATUS_FAILED;
    }

    /**
     * Check if the webhook is pending processing.
     */
    public function isPending(): bool
    {
        return $this->processing_status === self::STATUS_PENDING;
    }

    /**
     * Mark the webhook as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'processing_status' => self::STATUS_PROCESSED,
            'processed_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Mark the webhook as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'processing_status' => self::STATUS_FAILED,
            'processing_error' => $error,
            'processed_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
