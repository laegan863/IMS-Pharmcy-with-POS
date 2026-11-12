<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailySummary extends Notification
{
    use Queueable;

    /**
     * @param  list<array{name: string, invoices: int, net: string, discounts: string, voids: string, refunds: string, cash_variance: string, expiring_30_days: int}>  $branches
     */
    public function __construct(public string $date, public array $branches) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Daily summary for :date', ['date' => $this->date]))
            ->greeting(__('Summary for :date', ['date' => $this->date]));

        foreach ($this->branches as $branch) {
            $message
                ->line('**'.$branch['name'].'**')
                ->line(__(':invoices invoices · net sales ₱:net · senior/PWD discounts ₱:discounts', $branch))
                ->line(__('Voids ₱:voids · refunds ₱:refunds · cash over/short ₱:cash_variance', $branch))
                ->line(__(':count batch(es) expire within 30 days', ['count' => $branch['expiring_30_days']]));
        }

        return $message->action(__('Open the dashboard'), route('dashboard'));
    }
}
