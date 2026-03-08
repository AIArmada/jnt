<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Notifications;

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderShippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TrackingData $tracking,
        public readonly ?string $estimatedDelivery = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your Order Has Been Shipped')
            ->greeting('Good news!')
            ->line('Your order has been shipped and is on its way to you.')
            ->line('Tracking Number: ' . $this->tracking->trackingNumber);

        if ($this->tracking->orderId !== null) {
            $message->line('Order ID: ' . $this->tracking->orderId);
        }

        if ($this->estimatedDelivery !== null) {
            $message->line('Estimated Delivery: ' . $this->estimatedDelivery);
        }

        if ($this->tracking->details->count() > 0) {
            $latest = $this->tracking->getLatestDetail();
            if ($latest instanceof TrackingDetailData && ($latest->scanNetworkCity !== null || $latest->scanNetworkProvince !== null)) {
                $location = implode(', ', array_filter([
                    $latest->scanNetworkCity,
                    $latest->scanNetworkProvince,
                ]));
                $message->line('Current Location: ' . $location);
            }
        }

        return $message
            ->line('Thank you for your order!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $location = null;

        if ($this->tracking->details->count() > 0) {
            $latest = $this->tracking->getLatestDetail();
            if ($latest instanceof TrackingDetailData && ($latest->scanNetworkCity !== null || $latest->scanNetworkProvince !== null)) {
                $location = implode(', ', array_filter([
                    $latest->scanNetworkCity,
                    $latest->scanNetworkProvince,
                ]));
            }
        }

        return [
            'type' => 'order_shipped',
            'tracking_number' => $this->tracking->trackingNumber,
            'order_id' => $this->tracking->orderId,
            'estimated_delivery' => $this->estimatedDelivery,
            'current_location' => $location,
            'message' => 'Your order has been shipped and is on its way to you.',
        ];
    }
}
