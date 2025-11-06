<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Notifications;

use AIArmada\Jnt\Data\TrackingData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderProblemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TrackingData $tracking,
        public readonly ?string $supportContact = null
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
            ->subject('Issue with Your Order')
            ->greeting('Attention Required')
            ->line('There is an issue with your order that requires attention.')
            ->line('Tracking Number: '.$this->tracking->trackingNumber);

        if ($this->tracking->orderId !== null) {
            $message->line('Order ID: '.$this->tracking->orderId);
        }

        // Get problem details
        $details = $this->tracking->details;
        if ($details !== []) {
            $latest = end($details);
            if ($latest instanceof \AIArmada\Jnt\Data\TrackingDetailData) {
                $message->line('Issue: '.$latest->description);

                if ($latest->problemType !== null) {
                    $message->line('Problem Type: '.$latest->problemType);
                }

                if ($latest->remark !== null) {
                    $message->line('Details: '.$latest->remark);
                }

                $message->line('Reported At: '.$latest->scanTime);
            }
        }

        if ($this->supportContact !== null) {
            $message->line('For assistance, please contact: '.$this->supportContact);
        }

        return $message
            ->line('We apologize for any inconvenience and are working to resolve this issue.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $details = $this->tracking->details;
        $problemDescription = null;
        $problemType = null;
        $problemDetails = null;
        $reportedAt = null;

        if ($details !== []) {
            $latest = end($details);
            if ($latest instanceof \AIArmada\Jnt\Data\TrackingDetailData) {
                $problemDescription = $latest->description;
                $problemType = $latest->problemType;
                $problemDetails = $latest->remark;
                $reportedAt = $latest->scanTime;
            }
        }

        return [
            'type' => 'order_problem',
            'tracking_number' => $this->tracking->trackingNumber,
            'order_id' => $this->tracking->orderId,
            'problem_description' => $problemDescription,
            'problem_type' => $problemType,
            'problem_details' => $problemDetails,
            'reported_at' => $reportedAt,
            'support_contact' => $this->supportContact,
            'message' => 'There is an issue with your order that requires attention.',
        ];
    }
}
