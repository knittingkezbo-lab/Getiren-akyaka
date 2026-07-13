<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderNotification extends Notification
{
    /**
     * @param  array<int, string>  $channels  Gönderim kanalları (varsayılan: zil + e-posta)
     * @param  string|null  $url  Sabit link; null ise alıcının rolüne göre otomatik belirlenir
     * @param  string|null  $event  Olay anahtarı (assigned, on_the_way, …); alıcı bu olayı kapattıysa hiç gönderilmez
     */
    public function __construct(
        public Order $order,
        public string $title,
        public string $message,
        public array $channels = ['database', 'mail'],
        public ?string $url = null,
        public ?string $event = null,
    ) {}

    /** Olay tercihini uygular, ardından istenen kanalları alıcının kanal tercihleriyle kesiştirir. */
    public function via(object $notifiable): array
    {
        // Bu olay alıcı tarafından kapatılmışsa hiçbir kanaldan gönderme
        if ($this->event !== null && ($notifiable->notification_events[$this->event] ?? true) === false) {
            return [];
        }

        return array_values(array_filter($this->channels, fn (string $channel) => match ($channel) {
            'mail' => (bool) ($notifiable->notify_email ?? true),
            'database' => (bool) ($notifiable->notify_web ?? true),
            default => true,
        }));
    }

    /** Alıcı kurye ise kurye rotası, değilse müşteri rotası (override edilmediyse). */
    protected function targetUrl(object $notifiable): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        return $notifiable->role === UserRole::Courier
            ? "/kurye/is/{$this->order->id}"
            : "/musteri/siparisler/{$this->order->id}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'order_code' => $this->order->code,
            'url' => $this->targetUrl($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $notifiable->role === UserRole::Courier ? 'İşi gör' : 'Siparişi gör';

        return (new MailMessage)
            ->subject('Getiren Akyaka · '.$this->title)
            ->greeting('Merhaba '.$notifiable->name)
            ->line($this->message)
            ->action($label, url($this->targetUrl($notifiable)))
            ->line('Teşekkürler — Getiren Akyaka');
    }
}
