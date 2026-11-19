<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendApplicationNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $deliveryId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(): void
    {
        Cache::lock('delivery:'.$this->deliveryId, 45)->block(5, function (): void {
            $delivery = NotificationDelivery::findOrFail($this->deliveryId);
            if ($delivery->status === 'sent' || $delivery->channel !== 'email') {
                return;
            }
            $delivery->increment('attempts');
            try {
                Mail::raw($delivery->message.($delivery->access_url ? "\n\n".$delivery->access_url : ''), function (Message $mail) use ($delivery): void {
                    $mail->to($delivery->destination)->subject('Informasi pengajuan kemahasiswaan');
                });
                $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
            } catch (Throwable $exception) {
                $delivery->update(['status' => 'failed', 'last_error' => 'Pengiriman email gagal. Periksa konfigurasi pengirim.']);
                throw $exception;
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::whereKey($this->deliveryId)->where('status', '!=', 'sent')->update(['status' => 'failed', 'last_error' => 'Pengiriman gagal setelah percobaan ulang.']);
    }
}
