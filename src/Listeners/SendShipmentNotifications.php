<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Notifications\OrderDeliveredNotification;
use AIArmada\Jnt\Notifications\OrderProblemNotification;
use AIArmada\Jnt\Notifications\OrderShippedNotification;
use AIArmada\Jnt\Services\JntTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Spatie\LaravelData\DataCollection;
use Throwable;

/**
 * Listener that sends notifications when JNT order status changes.
 *
 * Dispatches appropriate notifications based on the current status:
 * - PickedUp/InTransit → OrderShippedNotification
 * - OutForDelivery → OrderShippedNotification (with "out for delivery" info)
 * - Delivered → OrderDeliveredNotification
 * - Exception/ReturnInitiated/Returned → OrderProblemNotification
 */
class SendShipmentNotifications implements ShouldQueue
{
    public function __construct(
        private readonly JntTrackingService $trackingService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(JntOrderStatusChanged $event): void
    {
        if (! config('jnt.notifications.enabled', true)) {
            return;
        }

        $owner = $event->owner();

        OwnerContext::withOwner($owner, function () use ($event): void {
            $order = $event->resolveOrder();

            if ($order === null) {
                return;
            }

            $notifiable = $this->resolveNotifiable($order);

            if ($notifiable === null) {
                return;
            }

            $notification = $this->createNotification($event, $order);

            if ($notification !== null) {
                Notification::send($notifiable, $notification);
            }
        });
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(JntOrderStatusChanged $event): bool
    {
        return config('jnt.notifications.queue', true);
    }

    /**
     * Resolve the notifiable entity from the order.
     */
    private function resolveNotifiable(JntOrder $order): ?object
    {
        // Try owner relationship
        $owner = $order->owner()->getResults();

        if ($owner !== null) {
            return $owner;
        }

        // Try to get notifiable from metadata
        $metadata = $order->metadata ?? [];
        if (isset($metadata['notification_email'])) {
            return new class($metadata['notification_email'])
            {
                public function __construct(public readonly string $email) {}

                /**
                 * @return array{mail: string}
                 */
                public function routeNotificationForMail(): array
                {
                    return ['mail' => $this->email];
                }
            };
        }

        return null;
    }

    /**
     * Create the appropriate notification based on status change.
     */
    private function createNotification(JntOrderStatusChanged $event, JntOrder $order): OrderShippedNotification | OrderDeliveredNotification | OrderProblemNotification | null
    {
        $trackingData = $this->getTrackingData($order);

        if ($trackingData === null) {
            return null;
        }

        return match ($event->currentStatus) {
            TrackingStatus::PickedUp,
            TrackingStatus::InTransit,
            TrackingStatus::AtHub => new OrderShippedNotification(
                tracking: $trackingData,
                estimatedDelivery: $this->getEstimatedDelivery($order)
            ),
            TrackingStatus::OutForDelivery => new OrderShippedNotification(
                tracking: $trackingData,
                estimatedDelivery: CarbonImmutable::now()->format('Y-m-d')
            ),
            TrackingStatus::Delivered => new OrderDeliveredNotification(
                tracking: $trackingData
            ),
            TrackingStatus::DeliveryAttempted,
            TrackingStatus::ReturnInitiated,
            TrackingStatus::Returned,
            TrackingStatus::Exception => new OrderProblemNotification(
                tracking: $trackingData,
                supportContact: config('jnt.notifications.support_contact')
            ),
            default => null,
        };
    }

    /**
     * Get tracking data for the order.
     */
    private function getTrackingData(JntOrder $order): ?TrackingData
    {
        try {
            $result = $this->trackingService->track(
                orderId: $order->order_id,
                trackingNumber: $order->tracking_number
            );

            $details = array_map(
                static fn (array $event): TrackingDetailData => $event['raw'],
                $result['events']
            );

            /** @var DataCollection<int, TrackingDetailData> $detailCollection */
            $detailCollection = new DataCollection(TrackingDetailData::class, $details);

            return new TrackingData(
                trackingNumber: $result['tracking_number'],
                orderId: $result['order_id'],
                details: $detailCollection
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get estimated delivery date for the order.
     */
    private function getEstimatedDelivery(JntOrder $order): ?string
    {
        // Calculate from default days since JntOrder doesn't have estimated_delivery_at
        $days = config('jnt.shipping.default_estimated_days', 3);

        return CarbonImmutable::now()->addDays($days)->format('Y-m-d');
    }
}
