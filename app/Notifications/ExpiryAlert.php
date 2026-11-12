<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Batches that reached an expiry alert threshold (90, 60 or 30 days by
 * default) or expired today, for one branch.
 */
class ExpiryAlert extends Notification
{
    use Queueable;

    /**
     * @param  list<array{product: string, lot: string, expires_on: string, days: int, quantity: int}>  $batches
     */
    public function __construct(public string $branchName, public array $batches) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Expiry alert: :count batch(es) at :branch', ['count' => count($this->batches), 'branch' => $this->branchName]))
            ->line(__('These batches reached an expiry alert today:'));

        foreach ($this->batches as $batch) {
            $message->line(sprintf('%s · lot %s · %s · %d on hand · %s',
                $batch['product'],
                $batch['lot'],
                $batch['expires_on'],
                $batch['quantity'],
                $batch['days'] < 0 ? __('expired') : __(':days days left', ['days' => $batch['days']]),
            ));
        }

        return $message->action(__('Open stock on hand'), route('stock.index', ['filter' => ['view' => 'near_expiry']]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['branch' => $this->branchName, 'batches' => $this->batches];
    }
}
