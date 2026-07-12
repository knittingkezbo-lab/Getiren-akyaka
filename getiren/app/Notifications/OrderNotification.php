<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderNotification extends Notification
{
    public function __construct(
        public Order $order,
        public string $title,
        public string $message,
    ) {}

    /** Hem uygulama-içi (database) hem e-posta (mail). */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'order_code' => $this->order->code,
            'url' => "/musteri/siparisler/{$this->order->id}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Getiren Akyaka · '.$this->title)
            ->greeting('Merhaba '.$notifiable->name)
            ->line($this->message)
            ->action('Siparişi gör', url("/musteri/siparisler/{$this->order->id}"))
            ->line('Teşekkürler — Getiren Akyaka');
    }
}
