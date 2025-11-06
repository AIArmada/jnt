<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Notifications;

use AIArmada\Jnt\Data\TrackingData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TrackingData $tracking) {}

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
            ->subject('Your Order Has Been Delivered')
            ->greeting('Great news!')
            ->line('Your order has been successfully delivered.')
            ->line('Tracking Number: '.$this->tracking->trackingNumber);

        if ($this->tracking->orderId !== null) {
            $message->line('Order ID: '.$this->tracking->orderId);
        }

        // Get delivery details
        $details = $this->tracking->details;
        if ($details !== []) {
            $latest = end($details);
            if ($latest instanceof \AIArmada\Jnt\Data\TrackingDetailData) {
                $message->line('Delivered At: '.$latest->scanTime);

                if ($latest->scanNetworkCity !== null || $latest->scanNetworkProvince !== null) {
                    $location = implode(', ', array_filter([
                        $latest->scanNetworkCity,
                        $latest->scanNetworkProvince,
                    ]));
                    $message->line('Delivery Location: '.$location);
                }

                if ($latest->staffName !== null) {
                    $message->line('Delivered By: '.$latest->staffName);
                }

                if ($latest->signaturePictureUrl !== null) {
                    $message->line('Signature Available: Yes');
                }
            }
        }

        return $message
            ->line('Thank you for your business!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $details = $this->tracking->details;
        $deliveryTime = null;
        $deliveryLocation = null;
        $deliveredBy = null;
        $hasSignature = false;

        if ($details !== []) {
            $latest = end($details);
            if ($latest instanceof \AIArmada\Jnt\Data\TrackingDetailData) {
                $deliveryTime = $latest->scanTime;
                $deliveredBy = $latest->staffName;
                $hasSignature = $latest->signaturePictureUrl !== null;

                if ($latest->scanNetworkCity !== null || $latest->scanNetworkProvince !== null) {
                    $deliveryLocation = implode(', ', array_filter([
                        $latest->scanNetworkCity,
                        $latest->scanNetworkProvince,
                    ]));
                }
            }
        }

        return [
            'type' => 'order_delivered',
            'tracking_number' => $this->tracking->trackingNumber,
            'order_id' => $this->tracking->orderId,
            'delivery_time' => $deliveryTime,
            'delivery_location' => $deliveryLocation,
            'delivered_by' => $deliveredBy,
            'has_signature' => $hasSignature,
            'message' => 'Your order has been successfully delivered.',
        ];
    }
}
